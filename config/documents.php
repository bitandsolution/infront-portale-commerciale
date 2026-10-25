<?php

/*
| Generation of the PDF documents (M09; progettazione-tecnica §5.5; fetta S1, D-308…D-313).
|
| HTML 1920×1080 → Chromium headless (Browsershot, chrome-headless-shell of Chrome for Testing
| installed by `npx puppeteer browsers install chrome-headless-shell`) → qpdf. The fonts are read
| only from files (D-249), never installed in the system: Interstate Regular, Bold and Black from
| DOCUMENT_FONTS_PATH, with the license of Infront (D-256), never in the repository.
*/

return [
    // Folder with the three Interstate files, named as below (rename them when copying them). Empty:
    // the free substitute font (DejaVu) is used, only in the local and testing environments (D-311);
    // anywhere else the generation stops with an error.
    'fonts_path' => env('DOCUMENT_FONTS_PATH') ?: null,

    // File names inside DOCUMENT_FONTS_PATH, by CSS weight (layout-dinamici §2.1: 400 Regular,
    // 700 Bold, 900 Black).
    'font_files' => [
        400 => 'interstate-regular.ttf',
        700 => 'interstate-bold.ttf',
        900 => 'interstate-black.ttf',
    ],

    // Free substitute font of tests, CI and of the VM without the Interstate files (D-311): DejaVu
    // Sans from the fonts-dejavu-core package. DejaVu has no Black: weight 900 uses the Bold file.
    'substitute_font_files' => [
        400 => env('DOCUMENT_SUBSTITUTE_FONT_REGULAR', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'),
        700 => env('DOCUMENT_SUBSTITUTE_FONT_BOLD', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'),
        900 => env('DOCUMENT_SUBSTITUTE_FONT_BOLD', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'),
    ],

    'chromium' => [
        // Node.js used by Browsershot, with puppeteer from the node_modules of the project; when the
        // worker does not have it in its PATH, the absolute path goes here (R1, D-223).
        'node_binary' => env('DOCUMENT_NODE_BINARY', 'node'),

        // Chromium's sandbox is required (R1). Docker's default seccomp profile does not let
        // Chromium create its sandbox, so in the Sail container (VM) it can be turned off; the
        // setting is refused outside the local and testing environments.
        'no_sandbox' => (bool) env('DOCUMENT_CHROMIUM_NO_SANDBOX', false),

        // Seconds allowed to Chromium for one document (progettazione-tecnica §5.5.1: the job of the
        // documents queue has 300 s in all).
        'timeout' => (int) env('DOCUMENT_CHROMIUM_TIMEOUT', 240),
    ],

    // Folder of the jobs: each generation writes its HTML, images and raw PDF into a new subfolder,
    // removed at the end (D-312).
    'work_path' => env('DOCUMENT_WORK_PATH', storage_path('framework/documents')),

    // Development only (D-309): folder with real JPEG images extracted from the example PDFs, used
    // by "documents:try --samples" on the VM; never in the repository.
    'samples_path' => env('DOCUMENT_SAMPLES_PATH') ?: null,

    // Command-line tools of the checks and of the optimisation (R1, D-308).
    'qpdf_binary' => env('DOCUMENT_QPDF_BINARY', 'qpdf'),
    'pdffonts_binary' => env('DOCUMENT_PDFFONTS_BINARY', 'pdffonts'),
    'pdfinfo_binary' => env('DOCUMENT_PDFINFO_BINARY', 'pdfinfo'),
];
