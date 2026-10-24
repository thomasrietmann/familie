import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { test } from "node:test";
import { extractFamilyCalendarSuggestions } from "../../resources/ai/familyCalendarExtractor.ts";

const context = {
  today: "2026-10-24", familyName: "Test family", documentTitle: "School",
  defaultTarget: { type: "family" as const, id: null, name: null }, parents: [], children: [],
};

for (const scenario of ["success", "invalid-json", "invalid-suggestions", "provider-error"] as const) {
  test(`CLI sends transient input and protects response contents: ${scenario}`, async (t) => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), "family-extraction-test-"));
    t.after(() => fs.rmSync(directory, { recursive: true, force: true }));
    const filePath = path.join(directory, "school.pdf");
    fs.writeFileSync(filePath, "%PDF-1.4 confidential document");
    const logs: unknown[] = [];
    const logger = {
      info: (...args: unknown[]) => logs.push(args),
      warn: (...args: unknown[]) => logs.push(args),
      error: (...args: unknown[]) => logs.push(args),
    };
    let calls = 0;
    t.mock.method(globalThis, "fetch", async (url, options) => {
      calls++;
      assert.equal(String(url), "https://api.openai.com/v1/responses");
      const body = JSON.parse(options.body);
      assert.equal(body.store, false);
      const file = body.input[1].content[1];
      assert.equal(file.type, "input_file");
      assert.equal(file.filename, "school.pdf");
      assert.equal(file.file_data, `data:application/pdf;base64,${Buffer.from("%PDF-1.4 confidential document").toString("base64")}`);
      assert.equal(file.file_id, undefined);
      if (scenario === "provider-error") {
        return new Response(JSON.stringify({ error: { message: "private-provider-response", type: "server_error" } }), { status: 500 });
      }
      const output = scenario === "invalid-json" ? "private-provider-response"
        : scenario === "invalid-suggestions" ? JSON.stringify({ suggestions: [{ title: "private-provider-response" }] })
        : JSON.stringify({ suggestions: [] });
      return new Response(JSON.stringify({ object: "response", output: [{ type: "message", role: "assistant", content: [{ type: "output_text", text: output, annotations: [] }] }] }), {
        status: 200, headers: { "Content-Type": "application/json" },
      });
    });
    const extraction = extractFamilyCalendarSuggestions({ filePath, context, apiKey: "test-only", logger });
    if (scenario === "success") {
      assert.deepEqual(await extraction, { suggestions: [] });
    } else {
      await assert.rejects(extraction, (error: Error) => {
        assert.ok(!error.message.includes("private-provider-response"));
        const expected = {
          "invalid-json": "OpenAI Antwort konnte nicht als JSON gelesen werden.",
          "invalid-suggestions": "OpenAI Antwort ist nicht valide:",
          "provider-error": "Dokumentanalyse nicht verfügbar.",
        };
        assert.ok(error.message.startsWith(expected[scenario]));
        return true;
      });
    }
    assert.equal(calls, 1, "No persistent Files API upload or automatic retry");
    assert.ok(!JSON.stringify(logs).includes("private-provider-response"));
    assert.ok(!JSON.stringify(logs).includes("confidential document"));
    assert.ok(fs.existsSync(filePath), "A selected local source file is not a temporary server upload");
  });
}
