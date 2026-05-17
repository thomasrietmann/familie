<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],

    'ocr' => [
        'tesseract_path' => env('TESSERACT_PATH', 'tesseract'),
        'pdftoppm_path' => env('PDFTOPPM_PATH', 'pdftoppm'),
        'language' => env('TESSERACT_LANG', 'deu+eng'),
        'timeout' => (int) env('OCR_TIMEOUT', 120),
        'pdf_dpi' => (int) env('OCR_PDF_DPI', 200),
        'pdf_max_pages' => (int) env('OCR_PDF_MAX_PAGES', 10),
    ],
];
