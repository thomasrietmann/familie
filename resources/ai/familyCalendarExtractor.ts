import fs from "node:fs";
import { fileURLToPath } from "node:url";
import OpenAI from "openai";

const CATEGORIES = [
  "school",
  "holiday",
  "birthday",
  "excursion",
  "parent_evening",
  "doctor",
  "sports",
  "deadline",
  "other",
] as const;

const OWNER_TYPES = ["child", "parent", "family"] as const;

type Category = (typeof CATEGORIES)[number];
type OwnerType = (typeof OWNER_TYPES)[number];

export type FamilyCalendarSuggestion = {
  title: string;
  description: string | null;
  starts_at: string;
  ends_at: string | null;
  all_day: boolean;
  location: string | null;
  category: Category;
  suggested_owner_type: OwnerType;
  suggested_owner_id: number | null;
  confidence: number;
};

export type FamilyCalendarExtraction = {
  suggestions: FamilyCalendarSuggestion[];
};

export type ExtractionContext = {
  today: string;
  familyName: string;
  documentTitle: string;
  defaultTarget: {
    type: OwnerType;
    id: number | null;
    name: string | null;
  };
  parents: Array<{ id: number; name: string }>;
  children: Array<{ id: number; name: string }>;
};

type Logger = {
  info: (message: string, meta?: unknown) => void;
  warn: (message: string, meta?: unknown) => void;
  error: (message: string, meta?: unknown) => void;
};

const consoleLogger: Logger = {
  info: (message, meta) => console.error(message, meta ?? ""),
  warn: (message, meta) => console.error(message, meta ?? ""),
  error: (message, meta) => console.error(message, meta ?? ""),
};

const systemPrompt = `
Du extrahierst Familien-Termine aus deutschsprachigen Dokumenten.
Analysiere das angehängte PDF/Bild direkt visuell. Verwende kein Markdown.
Prüfe jede sichtbare Zeile separat und extrahiere alle sichtbaren Termine.
Führe keine Termine zusammen. Wenn eine Zeile zwei Ereignisse enthält, erzeuge zwei separate Termine.
Wenn eine Zeile nur Informationstext ohne konkreten Termin enthält, ignoriere sie.
Nutze Europe/Zurich als Zeitzone.
Wenn eine Uhrzeit fehlt: all_day=true und starts_at auf 00:00 setzen.
Mehrtägige all_day-Termine müssen ends_at exklusiv setzen: letzter sichtbarer Tag + 1 Tag um 00:00.
Verwende nur diese Kategorien: school, holiday, birthday, excursion, parent_evening, doctor, sports, deadline, other.
Setze suggested_owner_type und suggested_owner_id standardmässig auf die vorgegebene Zielperson.
Ändere suggested_owner_type und suggested_owner_id nur, wenn das Dokument eindeutig eine bekannte Person aus der Familie nennt.
Geburtstage fremder Kinder oder fremder Personen nicht als Owner setzen; beim Standard-Ziel belassen.
Gib exakt JSON im Format {"suggestions":[...]} zurück.
`.trim();

const schema = {
  type: "object",
  additionalProperties: false,
  required: ["suggestions"],
  properties: {
    suggestions: {
      type: "array",
      items: {
        type: "object",
        additionalProperties: false,
        required: [
          "title",
          "description",
          "starts_at",
          "ends_at",
          "all_day",
          "location",
          "category",
          "suggested_owner_type",
          "suggested_owner_id",
          "confidence",
        ],
        properties: {
          title: { type: "string" },
          description: { type: ["string", "null"] },
          starts_at: { type: "string" },
          ends_at: { type: ["string", "null"] },
          all_day: { type: "boolean" },
          location: { type: ["string", "null"] },
          category: {
            type: "string",
            enum: CATEGORIES,
          },
          suggested_owner_type: {
            type: "string",
            enum: OWNER_TYPES,
          },
          suggested_owner_id: {
            type: ["integer", "null"],
          },
          confidence: {
            type: "number",
            minimum: 0,
            maximum: 1,
          },
        },
      },
    },
  },
} as const;

export async function extractFamilyCalendarSuggestions({
  filePath,
  context,
  apiKey = process.env.OPENAI_API_KEY,
  model = process.env.OPENAI_MODEL || "gpt-5.4",
  logger = consoleLogger,
}: {
  filePath: string;
  context: ExtractionContext;
  apiKey?: string;
  model?: string;
  logger?: Logger;
}): Promise<FamilyCalendarExtraction> {
  if (!apiKey) {
    throw new Error("OPENAI_API_KEY ist nicht gesetzt.");
  }

  if (!fs.existsSync(filePath)) {
    throw new Error(`Datei nicht gefunden: ${filePath}`);
  }

  const openai = new OpenAI({ apiKey });

  const file = await openai.files.create({
    file: fs.createReadStream(filePath),
    purpose: "assistants",
  });

  const response = await openai.responses.create({
    model,
    temperature: 0,
    input: [
      {
        role: "system",
        content: [
          {
            type: "input_text",
            text: systemPrompt,
          },
        ],
      },
      {
        role: "user",
        content: [
          {
            type: "input_text",
            text: userPrompt(context),
          },
          {
            type: "input_file",
            file_id: file.id,
          },
        ],
      },
    ],
    text: {
      format: {
        type: "json_schema",
        name: "family_calendar_suggestions",
        strict: true,
        schema,
      },
    },
  });

  logger.info("OpenAI raw response", response);

  if (!response.output_text || response.output_text.trim() === "") {
    throw new Error("OpenAI Antwort enthält kein output_text.");
  }

  let parsed: unknown;

  try {
    parsed = JSON.parse(response.output_text);
  } catch (error) {
    logger.error("OpenAI JSON parse failed", {
      error: error instanceof Error ? error.message : String(error),
      output_text: response.output_text,
    });
    throw new Error("OpenAI Antwort konnte nicht als JSON gelesen werden.");
  }

  const validationErrors = validateExtraction(parsed);

  if (validationErrors.length > 0) {
    logger.warn("OpenAI validation failed", {
      validationErrors,
      output_text: response.output_text,
    });
    throw new Error(`OpenAI Antwort ist nicht valide: ${validationErrors.join(" ")}`);
  }

  return parsed as FamilyCalendarExtraction;
}

function userPrompt(context: ExtractionContext): string {
  return `Heute ist ${context.today}.
Familie: ${context.familyName}.
Dokumenttitel: ${context.documentTitle}.
Standard-Ziel: ${JSON.stringify(context.defaultTarget)}
Eltern: ${JSON.stringify(context.parents)}
Kinder: ${JSON.stringify(context.children)}

Extrahiere alle Termine aus dem angehängten Dokument.`;
}

function validateExtraction(value: unknown): string[] {
  const errors: string[] = [];

  if (!isRecord(value)) {
    return ["Antwort ist kein Objekt."];
  }

  if (!Array.isArray(value.suggestions)) {
    return ["suggestions fehlt oder ist kein Array."];
  }

  value.suggestions.forEach((suggestion, index) => {
    if (!isRecord(suggestion)) {
      errors.push(`suggestions.${index} ist kein Objekt.`);
      return;
    }

    for (const field of [
      "title",
      "description",
      "starts_at",
      "ends_at",
      "all_day",
      "location",
      "category",
      "suggested_owner_type",
      "suggested_owner_id",
      "confidence",
    ]) {
      if (!(field in suggestion)) {
        errors.push(`suggestions.${index}.${field} fehlt.`);
      }
    }

    if (typeof suggestion.title !== "string" || suggestion.title.trim() === "") {
      errors.push(`suggestions.${index}.title ist leer.`);
    }

    if (typeof suggestion.title === "string" && /\s+\/\s+/.test(suggestion.title)) {
      errors.push(`suggestions.${index}.title wirkt kombiniert und muss getrennt werden.`);
    }

    if (typeof suggestion.starts_at !== "string" || suggestion.starts_at.trim() === "") {
      errors.push(`suggestions.${index}.starts_at darf nicht leer sein.`);
    }

    if (typeof suggestion.all_day !== "boolean") {
      errors.push(`suggestions.${index}.all_day ist kein Boolean.`);
    }

    if (suggestion.all_day === true && typeof suggestion.starts_at === "string" && !isMidnight(suggestion.starts_at)) {
      errors.push(`suggestions.${index}.starts_at muss bei all_day=true auf 00:00 stehen.`);
    }

    if (!CATEGORIES.includes(suggestion.category as Category)) {
      errors.push(`suggestions.${index}.category ist nicht erlaubt.`);
    }

    if (!OWNER_TYPES.includes(suggestion.suggested_owner_type as OwnerType)) {
      errors.push(`suggestions.${index}.suggested_owner_type ist nicht erlaubt.`);
    }

    if (
      suggestion.suggested_owner_id !== null &&
      (!Number.isInteger(suggestion.suggested_owner_id) || suggestion.suggested_owner_id < 1)
    ) {
      errors.push(`suggestions.${index}.suggested_owner_id ist nicht gültig.`);
    }

    if (typeof suggestion.confidence !== "number" || suggestion.confidence < 0 || suggestion.confidence > 1) {
      errors.push(`suggestions.${index}.confidence ist nicht zwischen 0 und 1.`);
    }
  });

  return errors;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function isMidnight(value: string): boolean {
  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return false;
  }

  return /T00:00(?::00)?(?:[+-]\d{2}:\d{2}|Z)?$/.test(value);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const [, , filePath] = process.argv;

  if (!filePath) {
    throw new Error("Usage: npm run extract:events -- /path/to/document.pdf");
  }

  const result = await extractFamilyCalendarSuggestions({
    filePath,
    context: {
      today: new Date().toISOString().slice(0, 10),
      familyName: "Familie Rietmann",
      documentTitle: "Test",
      defaultTarget: { type: "family", id: null, name: "Ganze Familie" },
      parents: [],
      children: [],
    },
  });

  process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
}
