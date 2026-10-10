<?php

use App\Services\Pdf\Processors;

/*
|--------------------------------------------------------------------------
| Tool registry
|--------------------------------------------------------------------------
|
| Every tool of the site is declared here. Routes, the "All tools" page, the
| sitemap, upload validation, option validation and the tool UI are all
| generated from this file, so adding a tool is a matter of adding an entry
| plus a server processor and/or a browser handler.
|
| Keys:
|   name, description           Display name and one-line description.
|   title, meta, keywords       SEO overrides (sensible defaults are generated).
|   category / also_in          Category key(s) used by the tools directory.
|   icon                        Icon name (resources/views/components/icon.blade.php).
|   ui                          generic | editor | organize | text | translator
|   accept                      Input groups: pdf, image, word, excel, powerpoint, html, text
|   multiple, min_files, max_files, sortable
|   engines                     Ordered list of "browser" and/or "server".
|   browser                     Browser handler module (resources/js/tools/{name}.js).
|   processor                   Server processor class.
|   params                      Fixed options passed to the handler / processor.
|   options                     Option fields (see resources/views/tools/partials/field.blade.php).
|   action                      Label of the main button.
|   popular                     Show on the home page.
|
*/

$pagesField = [
    'type' => 'pages',
    'label' => 'Pages',
    'placeholder' => 'e.g. 1-3, 5, 8-10',
    'help' => 'Leave empty for all pages. Use commas and ranges.',
];

$pageSizeField = [
    'type' => 'select',
    'label' => 'Page size',
    'default' => 'a4',
    'choices' => ['a4' => 'A4', 'letter' => 'US Letter', 'legal' => 'US Legal', 'a3' => 'A3', 'a5' => 'A5'],
];

$orientationField = [
    'type' => 'choice',
    'label' => 'Orientation',
    'default' => 'portrait',
    'choices' => ['portrait' => 'Portrait', 'landscape' => 'Landscape'],
];

$imageToPdfOptions = [
    'page_size' => [
        'type' => 'select',
        'label' => 'Page size',
        'default' => 'a4',
        'choices' => ['a4' => 'A4', 'letter' => 'US Letter', 'fit' => 'Original image size'],
    ],
    'orientation' => [
        'type' => 'choice',
        'label' => 'Orientation',
        'default' => 'auto',
        'choices' => ['auto' => 'Auto', 'portrait' => 'Portrait', 'landscape' => 'Landscape'],
        'show_if' => ['page_size' => ['a4', 'letter']],
    ],
    'margin' => [
        'type' => 'choice',
        'label' => 'Margin',
        'default' => 'small',
        'choices' => ['none' => 'No margin', 'small' => 'Small', 'large' => 'Large'],
        'show_if' => ['page_size' => ['a4', 'letter']],
    ],
    'merge' => [
        'type' => 'toggle',
        'label' => 'Merge all images into one PDF',
        'default' => true,
    ],
];

$pdfToImageOptions = fn (string $format) => [
    'format' => [
        'type' => 'choice',
        'label' => 'Image format',
        'default' => $format,
        'choices' => ['jpg' => 'JPG', 'png' => 'PNG', 'webp' => 'WEBP'],
    ],
    'dpi' => [
        'type' => 'choice',
        'label' => 'Resolution',
        'default' => '150',
        'choices' => ['72' => 'Low (72 DPI)', '150' => 'Medium (150 DPI)', '300' => 'High (300 DPI)'],
    ],
    'quality' => [
        'type' => 'range',
        'label' => 'Image quality',
        'default' => 85,
        'min' => 30,
        'max' => 100,
        'step' => 5,
        'unit' => '%',
        'show_if' => ['format' => ['jpg', 'webp']],
    ],
    'pages' => $pagesField,
];

$compressOptions = fn (string $default) => [
    'level' => [
        'type' => 'choice',
        'label' => 'Compression level',
        'default' => $default,
        'cards' => true,
        'choices' => [
            'low' => ['Low compression', 'Best quality, smaller reduction'],
            'medium' => ['Medium compression', 'Good quality, good compression'],
            'high' => ['High compression', 'Smallest file, lower image quality'],
        ],
    ],
];

$protectOptions = [
    'password' => [
        'type' => 'password',
        'label' => 'Password',
        'required' => true,
        'confirm' => true,
        'min' => 4,
        'max' => 128,
        'help' => 'Anyone opening the PDF will need this password.',
    ],
    'allow_printing' => ['type' => 'toggle', 'label' => 'Allow printing', 'default' => true],
    'allow_copying' => ['type' => 'toggle', 'label' => 'Allow copying text and images', 'default' => false],
    'allow_modifying' => ['type' => 'toggle', 'label' => 'Allow editing and annotations', 'default' => false],
];

return [

    // Main navigation (header), in this order. "Home" and "All Tools" are added automatically.
    'navigation' => ['pdf-editor', 'merge-pdf', 'split-pdf', 'compress-pdf', 'pdf-to-word', 'pdf-to-text', 'pdf-translator'],

    'categories' => [
        'conversion' => [
            'name' => 'PDF Conversion',
            'description' => 'Convert PDF files to Word, Excel, PowerPoint, images, text and HTML.',
            'icon' => 'convert',
            'color' => '#2563eb',
        ],
        'creation' => [
            'name' => 'Create PDF',
            'description' => 'Turn images, Office documents, HTML and plain text into PDF.',
            'icon' => 'file-plus',
            'color' => '#059669',
        ],
        'editing' => [
            'name' => 'PDF Editing',
            'description' => 'Edit, annotate, sign and watermark PDF documents in your browser.',
            'icon' => 'edit',
            'color' => '#7c3aed',
        ],
        'organize' => [
            'name' => 'PDF Organization',
            'description' => 'Merge, split, reorder, rotate, extract and delete PDF pages.',
            'icon' => 'layers',
            'color' => '#ea580c',
        ],
        'optimize' => [
            'name' => 'PDF Compression',
            'description' => 'Compress, optimize, flatten and convert PDFs to grayscale.',
            'icon' => 'compress',
            'color' => '#16a34a',
        ],
        'security' => [
            'name' => 'PDF Security',
            'description' => 'Protect, encrypt and unlock PDFs and manage document metadata.',
            'icon' => 'lock',
            'color' => '#475569',
        ],
        'ocr' => [
            'name' => 'PDF OCR & Text',
            'description' => 'Recognise scanned text, extract text and images, search and replace.',
            'icon' => 'scan',
            'color' => '#0891b2',
        ],
        'translation' => [
            'name' => 'PDF Translation',
            'description' => 'Translate PDF documents into 16 languages with open-source engines.',
            'icon' => 'translate',
            'color' => '#4f46e5',
        ],
        'image' => [
            'name' => 'Image Tools',
            'description' => 'Convert images to PDF, compress and resize images.',
            'icon' => 'image',
            'color' => '#db2777',
        ],
    ],

    'tools' => [

        /* ------------------------------------------------------------------
         | PDF Conversion
         * ------------------------------------------------------------------ */

        'pdf-to-word' => [
            'name' => 'PDF to Word',
            'description' => 'Convert PDF files into editable Word (DOCX) documents.',
            'keywords' => 'pdf to word, pdf to docx, convert pdf to word, editable word document',
            'category' => 'conversion',
            'icon' => 'file-word',
            'accept' => ['pdf'],
            'engines' => ['server'],
            'processor' => Processors\PdfToWordProcessor::class,
            'action' => 'Convert to Word',
            'popular' => true,
            'options' => [
                'mode' => [
                    'type' => 'choice',
                    'label' => 'Conversion mode',
                    'default' => 'flow',
                    'cards' => true,
                    'choices' => [
                        'flow' => ['Editable text', 'Rebuilds paragraphs and headings – best for editing'],
                        'layout' => ['Keep layout', 'Positions text like the original (needs LibreOffice)'],
                    ],
                    'requires' => ['layout' => 'libreoffice'],
                ],
            ],
            'faq' => [
                ['Will the Word document be editable?', 'Yes. The text of your PDF is rebuilt as real paragraphs and headings that you can edit in Microsoft Word, LibreOffice or Google Docs.'],
                ['Can scanned PDFs be converted to Word?', 'Scanned PDFs only contain images. Run them through the OCR PDF tool first to recognise the text, then convert the result to Word.'],
            ],
        ],

        'pdf-to-excel' => [
            'name' => 'PDF to Excel',
            'description' => 'Extract tables from PDF into Excel (XLSX) spreadsheets.',
            'keywords' => 'pdf to excel, pdf to xlsx, extract tables from pdf, pdf table converter',
            'category' => 'conversion',
            'icon' => 'file-excel',
            'accept' => ['pdf'],
            'engines' => ['server'],
            'processor' => Processors\PdfToSpreadsheetProcessor::class,
            'params' => ['format' => 'xlsx'],
            'action' => 'Convert to Excel',
            'popular' => true,
            'options' => [
                'sheets' => [
                    'type' => 'choice',
                    'label' => 'Worksheets',
                    'default' => 'per_page',
                    'choices' => ['per_page' => 'One sheet per page', 'single' => 'Everything in one sheet'],
                ],
                'numbers' => ['type' => 'toggle', 'label' => 'Convert numbers to numeric cells', 'default' => true],
            ],
        ],

        'pdf-to-powerpoint' => [
            'name' => 'PDF to PowerPoint',
            'description' => 'Turn every PDF page into a PowerPoint (PPTX) slide.',
            'keywords' => 'pdf to powerpoint, pdf to pptx, pdf to slides, convert pdf to ppt',
            'category' => 'conversion',
            'icon' => 'file-ppt',
            'accept' => ['pdf'],
            'engines' => ['server', 'browser'],
            'browser' => 'pdf-to-pptx',
            'processor' => Processors\PdfToPowerpointProcessor::class,
            'action' => 'Convert to PowerPoint',
            'options' => [
                'quality' => [
                    'type' => 'choice',
                    'label' => 'Slide image quality',
                    'default' => 'standard',
                    'choices' => ['standard' => 'Standard', 'high' => 'High (larger file)'],
                ],
            ],
        ],

        'pdf-to-jpg' => [
            'name' => 'PDF to JPG',
            'description' => 'Convert each PDF page into a high-quality JPG image.',
            'keywords' => 'pdf to jpg, pdf to jpeg, pdf to image, convert pdf pages to images',
            'category' => 'conversion',
            'also_in' => ['image'],
            'icon' => 'file-image',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'pdf-to-image',
            'processor' => Processors\PdfToImageProcessor::class,
            'action' => 'Convert to images',
            'popular' => true,
            'options' => $pdfToImageOptions('jpg'),
        ],

        'pdf-to-png' => [
            'name' => 'PDF to PNG',
            'description' => 'Convert PDF pages into lossless PNG images.',
            'keywords' => 'pdf to png, convert pdf to png, pdf page to image',
            'category' => 'conversion',
            'also_in' => ['image'],
            'icon' => 'file-image',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'pdf-to-image',
            'processor' => Processors\PdfToImageProcessor::class,
            'action' => 'Convert to images',
            'options' => $pdfToImageOptions('png'),
        ],

        'pdf-to-webp' => [
            'name' => 'PDF to WEBP',
            'description' => 'Convert PDF pages into small, modern WEBP images.',
            'keywords' => 'pdf to webp, convert pdf to webp',
            'category' => 'conversion',
            'also_in' => ['image'],
            'icon' => 'file-image',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'pdf-to-image',
            'processor' => Processors\PdfToImageProcessor::class,
            'action' => 'Convert to images',
            'options' => $pdfToImageOptions('webp'),
        ],

        'pdf-to-text' => [
            'name' => 'PDF to Text',
            'description' => 'Extract all text from a PDF, edit it and download as TXT, DOCX or PDF.',
            'keywords' => 'pdf to text, pdf to txt, extract text from pdf, copy text from pdf',
            'category' => 'conversion',
            'also_in' => ['ocr'],
            'icon' => 'file-text',
            'ui' => 'text',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'pdf-to-text',
            'processor' => Processors\PdfToTextProcessor::class,
            'options' => ['layout' => ['type' => 'toggle', 'label' => 'Keep layout', 'default' => false]],
            'action' => 'Extract text',
            'popular' => true,
            'nav' => true,
            'faq' => [
                ['Does it work with scanned PDFs?', 'Yes. When a page has no text layer, the tool can run OCR (optical character recognition) to read the text from the page image.'],
                ['Can I edit the extracted text?', 'Yes. The text opens in a large editor where you can search, replace and edit it before downloading it as TXT, DOCX or PDF.'],
            ],
        ],

        'pdf-to-html' => [
            'name' => 'PDF to HTML',
            'description' => 'Convert PDF documents into HTML web pages.',
            'keywords' => 'pdf to html, convert pdf to web page, pdf to html5',
            'category' => 'conversion',
            'icon' => 'file-code',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'pdf-to-html',
            'processor' => Processors\PdfToHtmlProcessor::class,
            'action' => 'Convert to HTML',
            'options' => [
                'mode' => [
                    'type' => 'choice',
                    'label' => 'Output style',
                    'default' => 'layout',
                    'cards' => true,
                    'choices' => [
                        'layout' => ['Keep layout', 'Text positioned like in the PDF'],
                        'text' => ['Reflowable', 'Clean paragraphs that adapt to any screen'],
                    ],
                ],
            ],
        ],

        'pdf-to-csv' => [
            'name' => 'PDF to CSV',
            'description' => 'Extract table data from PDF into a CSV file.',
            'keywords' => 'pdf to csv, extract data from pdf, pdf table to csv',
            'category' => 'conversion',
            'icon' => 'file-csv',
            'accept' => ['pdf'],
            'engines' => ['server'],
            'processor' => Processors\PdfToSpreadsheetProcessor::class,
            'params' => ['format' => 'csv'],
            'action' => 'Convert to CSV',
            'options' => [
                'delimiter' => [
                    'type' => 'select',
                    'label' => 'Delimiter',
                    'default' => 'comma',
                    'choices' => ['comma' => 'Comma (,)', 'semicolon' => 'Semicolon (;)', 'tab' => 'Tab'],
                ],
                'numbers' => ['type' => 'toggle', 'label' => 'Normalise numbers (1,234.50 → 1234.50)', 'default' => false],
            ],
        ],

        /* ------------------------------------------------------------------
         | Create PDF
         * ------------------------------------------------------------------ */

        'jpg-to-pdf' => [
            'name' => 'JPG to PDF',
            'description' => 'Convert JPG images to PDF. Reorder images and choose the page size.',
            'keywords' => 'jpg to pdf, jpeg to pdf, image to pdf, photos to pdf',
            'category' => 'creation',
            'also_in' => ['image'],
            'icon' => 'image-pdf',
            'accept' => ['image'],
            'multiple' => true,
            'sortable' => true,
            'max_files' => 50,
            'engines' => ['browser', 'server'],
            'browser' => 'images-to-pdf',
            'processor' => Processors\ImagesToPdfProcessor::class,
            'action' => 'Convert to PDF',
            'popular' => true,
            'options' => $imageToPdfOptions,
        ],

        'png-to-pdf' => [
            'name' => 'PNG to PDF',
            'description' => 'Convert PNG images and screenshots into a PDF document.',
            'keywords' => 'png to pdf, screenshot to pdf, convert png to pdf',
            'category' => 'creation',
            'also_in' => ['image'],
            'icon' => 'image-pdf',
            'accept' => ['image'],
            'multiple' => true,
            'sortable' => true,
            'max_files' => 50,
            'engines' => ['browser', 'server'],
            'browser' => 'images-to-pdf',
            'processor' => Processors\ImagesToPdfProcessor::class,
            'action' => 'Convert to PDF',
            'options' => $imageToPdfOptions,
        ],

        'webp-to-pdf' => [
            'name' => 'WEBP to PDF',
            'description' => 'Convert WEBP images into a PDF document.',
            'keywords' => 'webp to pdf, convert webp to pdf',
            'category' => 'creation',
            'also_in' => ['image'],
            'icon' => 'image-pdf',
            'accept' => ['image'],
            'multiple' => true,
            'sortable' => true,
            'max_files' => 50,
            'engines' => ['browser', 'server'],
            'browser' => 'images-to-pdf',
            'processor' => Processors\ImagesToPdfProcessor::class,
            'action' => 'Convert to PDF',
            'options' => $imageToPdfOptions,
        ],

        'word-to-pdf' => [
            'name' => 'Word to PDF',
            'description' => 'Convert Word documents (DOC, DOCX, ODT, RTF) to PDF.',
            'keywords' => 'word to pdf, docx to pdf, doc to pdf, convert word to pdf',
            'category' => 'creation',
            'icon' => 'file-word',
            'accept' => ['word'],
            'multiple' => true,
            'max_files' => 10,
            'engines' => ['server'],
            'processor' => Processors\OfficeToPdfProcessor::class,
            'params' => ['kind' => 'word'],
            'action' => 'Convert to PDF',
            'popular' => true,
        ],

        'excel-to-pdf' => [
            'name' => 'Excel to PDF',
            'description' => 'Convert Excel spreadsheets (XLS, XLSX, ODS, CSV) to PDF.',
            'keywords' => 'excel to pdf, xlsx to pdf, spreadsheet to pdf',
            'category' => 'creation',
            'icon' => 'file-excel',
            'accept' => ['excel'],
            'multiple' => true,
            'max_files' => 10,
            'engines' => ['server'],
            'processor' => Processors\OfficeToPdfProcessor::class,
            'params' => ['kind' => 'excel'],
            'action' => 'Convert to PDF',
        ],

        'powerpoint-to-pdf' => [
            'name' => 'PowerPoint to PDF',
            'description' => 'Convert PowerPoint presentations (PPT, PPTX, ODP) to PDF.',
            'keywords' => 'powerpoint to pdf, pptx to pdf, ppt to pdf, slides to pdf',
            'category' => 'creation',
            'icon' => 'file-ppt',
            'accept' => ['powerpoint'],
            'multiple' => true,
            'max_files' => 10,
            'engines' => ['server'],
            'processor' => Processors\OfficeToPdfProcessor::class,
            'params' => ['kind' => 'powerpoint'],
            'action' => 'Convert to PDF',
        ],

        'html-to-pdf' => [
            'name' => 'HTML to PDF',
            'description' => 'Convert an HTML file or pasted HTML code into a PDF.',
            'keywords' => 'html to pdf, convert html to pdf, web page to pdf',
            'category' => 'creation',
            'icon' => 'file-code',
            'accept' => ['html'],
            'files_optional' => 'source:paste',
            'engines' => ['server'],
            'processor' => Processors\HtmlToPdfProcessor::class,
            'action' => 'Convert to PDF',
            'options' => [
                'source' => [
                    'type' => 'choice',
                    'label' => 'Source',
                    'default' => 'file',
                    'choices' => ['file' => 'Upload HTML file', 'paste' => 'Paste HTML code'],
                ],
                'html' => [
                    'type' => 'textarea',
                    'label' => 'HTML code',
                    'rows' => 10,
                    'max' => 500000,
                    'placeholder' => '<h1>Hello</h1><p>Paste your HTML here…</p>',
                    'show_if' => ['source' => ['paste']],
                ],
                'page_size' => $pageSizeField,
                'orientation' => $orientationField,
            ],
        ],

        'text-to-pdf' => [
            'name' => 'Text to PDF',
            'description' => 'Convert plain text (TXT) into a clean PDF document.',
            'keywords' => 'text to pdf, txt to pdf, convert text file to pdf',
            'category' => 'creation',
            'icon' => 'file-text',
            'accept' => ['text'],
            'files_optional' => 'source:paste',
            'engines' => ['server'],
            'processor' => Processors\TextToPdfProcessor::class,
            'action' => 'Convert to PDF',
            'options' => [
                'source' => [
                    'type' => 'choice',
                    'label' => 'Source',
                    'default' => 'file',
                    'choices' => ['file' => 'Upload TXT file', 'paste' => 'Type or paste text'],
                ],
                'text' => [
                    'type' => 'textarea',
                    'label' => 'Text',
                    'rows' => 10,
                    'max' => 1000000,
                    'placeholder' => 'Type or paste your text here…',
                    'show_if' => ['source' => ['paste']],
                ],
                'font' => [
                    'type' => 'select',
                    'label' => 'Font',
                    'default' => 'sans',
                    'choices' => ['sans' => 'Sans-serif', 'serif' => 'Serif', 'mono' => 'Monospace'],
                ],
                'font_size' => ['type' => 'number', 'label' => 'Font size', 'default' => 11, 'min' => 6, 'max' => 48, 'unit' => 'pt'],
                'page_size' => $pageSizeField,
                'orientation' => $orientationField,
            ],
        ],

        /* ------------------------------------------------------------------
         | PDF Editing (browser editor)
         * ------------------------------------------------------------------ */

        'pdf-editor' => [
            'name' => 'PDF Editor',
            'description' => 'Edit PDF files online: add text, images, shapes, drawings and signatures.',
            'title' => 'Free Online PDF Editor – Edit PDF Files in Your Browser',
            'keywords' => 'pdf editor, edit pdf online, free pdf editor, online pdf editor, annotate pdf',
            'category' => 'editing',
            'icon' => 'edit',
            'ui' => 'editor',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
            'popular' => true,
            'nav' => true,
            'faq' => [
                ['Is the PDF editor really free?', 'Yes. All editing tools are free to use without registration, watermarks or limits.'],
                ['Are my files uploaded?', 'No. The editor runs completely in your browser. Your PDF never leaves your device.'],
                ['Can I edit existing text?', 'Yes, where technically possible. Choose "Edit text", click any line of text and type. The original text is covered and replaced in the downloaded PDF.'],
            ],
        ],

        'edit-pdf' => [
            'name' => 'Edit PDF',
            'description' => 'Change existing text and add new content to your PDF.',
            'keywords' => 'edit pdf, edit pdf text, change text in pdf, modify pdf',
            'category' => 'editing',
            'icon' => 'edit-text',
            'ui' => 'editor',
            'params' => ['preset' => 'edit-text'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
        ],

        'add-text-to-pdf' => [
            'name' => 'Add Text to PDF',
            'description' => 'Type text anywhere on a PDF with your choice of font, size and color.',
            'keywords' => 'add text to pdf, type on pdf, write on pdf, fill pdf',
            'category' => 'editing',
            'icon' => 'type',
            'ui' => 'editor',
            'params' => ['preset' => 'text'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
        ],

        'add-image-to-pdf' => [
            'name' => 'Add Image to PDF',
            'description' => 'Insert images, logos or photos into any page of a PDF.',
            'keywords' => 'add image to pdf, insert picture in pdf, put logo on pdf',
            'category' => 'editing',
            'icon' => 'image',
            'ui' => 'editor',
            'params' => ['preset' => 'image'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
        ],

        'draw-on-pdf' => [
            'name' => 'Draw on PDF',
            'description' => 'Draw freehand on PDF pages with a pen of any color and size.',
            'keywords' => 'draw on pdf, freehand pdf, pdf pen, scribble on pdf',
            'category' => 'editing',
            'icon' => 'pen',
            'ui' => 'editor',
            'params' => ['preset' => 'draw'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
        ],

        'highlight-pdf' => [
            'name' => 'Highlight PDF',
            'description' => 'Highlight text in PDF documents with a marker of your choice.',
            'keywords' => 'highlight pdf, highlight text in pdf, pdf highlighter',
            'category' => 'editing',
            'icon' => 'highlighter',
            'ui' => 'editor',
            'params' => ['preset' => 'highlight'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
        ],

        'underline-pdf' => [
            'name' => 'Underline PDF',
            'description' => 'Underline important text in your PDF documents.',
            'keywords' => 'underline pdf, underline text in pdf',
            'category' => 'editing',
            'icon' => 'underline',
            'ui' => 'editor',
            'params' => ['preset' => 'underline'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
        ],

        'strikethrough-pdf' => [
            'name' => 'Strikethrough PDF',
            'description' => 'Strike through text in PDF files to mark deletions.',
            'keywords' => 'strikethrough pdf, strike out text in pdf, cross out pdf',
            'category' => 'editing',
            'icon' => 'strikethrough',
            'ui' => 'editor',
            'params' => ['preset' => 'strike'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
        ],

        'add-shapes-to-pdf' => [
            'name' => 'Add Shapes to PDF',
            'description' => 'Add rectangles, circles, lines and arrows to PDF pages.',
            'keywords' => 'add shapes to pdf, draw rectangle on pdf, add arrow to pdf',
            'category' => 'editing',
            'icon' => 'shapes',
            'ui' => 'editor',
            'params' => ['preset' => 'rect'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
        ],

        'sign-pdf' => [
            'name' => 'Sign PDF',
            'description' => 'Draw, type or upload your signature and place it on a PDF.',
            'title' => 'Sign PDF Online – Free Electronic Signature',
            'keywords' => 'sign pdf, esign pdf, electronic signature, add signature to pdf',
            'category' => 'editing',
            'icon' => 'signature',
            'ui' => 'editor',
            'params' => ['preset' => 'signature'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open editor',
            'popular' => true,
            'faq' => [
                ['How do I create my signature?', 'You can draw it with your mouse or finger, type your name and choose a handwriting font, or upload a photo of your signature.'],
                ['Is my signature stored?', 'No. Your signature is created and placed in your browser and never uploaded to our servers.'],
            ],
        ],

        'add-watermark' => [
            'name' => 'Add Watermark',
            'description' => 'Stamp text or an image watermark over your PDF pages.',
            'keywords' => 'add watermark to pdf, watermark pdf, stamp pdf, pdf watermark',
            'category' => 'editing',
            'icon' => 'watermark',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'browser' => 'watermark',
            'action' => 'Add watermark',
            'options' => [
                'type' => [
                    'type' => 'choice',
                    'label' => 'Watermark type',
                    'default' => 'text',
                    'choices' => ['text' => 'Text', 'image' => 'Image'],
                ],
                'text' => [
                    'type' => 'text',
                    'label' => 'Watermark text',
                    'default' => 'CONFIDENTIAL',
                    'max' => 200,
                    'show_if' => ['type' => ['text']],
                ],
                'image' => [
                    'type' => 'file',
                    'label' => 'Watermark image (PNG or JPG)',
                    'accept' => 'image/png,image/jpeg',
                    'show_if' => ['type' => ['image']],
                ],
                'font_size' => ['type' => 'number', 'label' => 'Font size', 'default' => 48, 'min' => 6, 'max' => 300, 'unit' => 'pt', 'show_if' => ['type' => ['text']]],
                'color' => ['type' => 'color', 'label' => 'Color', 'default' => '#dc2626', 'show_if' => ['type' => ['text']]],
                'scale' => ['type' => 'range', 'label' => 'Image size', 'default' => 40, 'min' => 5, 'max' => 100, 'unit' => '% of page width', 'show_if' => ['type' => ['image']]],
                'opacity' => ['type' => 'range', 'label' => 'Opacity', 'default' => 30, 'min' => 5, 'max' => 100, 'unit' => '%'],
                'rotation' => ['type' => 'range', 'label' => 'Rotation', 'default' => 45, 'min' => -180, 'max' => 180, 'step' => 5, 'unit' => '°'],
                'position' => ['type' => 'position', 'label' => 'Position', 'default' => 'middle-center', 'tile' => true],
                'pages' => $pagesField,
            ],
        ],

        'remove-watermark' => [
            'name' => 'Remove Watermark',
            'description' => 'Remove watermark annotations, layers and watermark artifacts from PDFs.',
            'keywords' => 'remove watermark from pdf, delete pdf watermark, pdf watermark remover',
            'category' => 'editing',
            'icon' => 'watermark-off',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'browser' => 'remove-watermark',
            'action' => 'Remove watermark',
            'notice' => 'Works for watermarks added as annotations, layers or tagged watermark artifacts (e.g. by Acrobat, Word and most PDF tools). A watermark that is part of a scanned image cannot be removed.',
            'options' => [
                'annotations' => ['type' => 'toggle', 'label' => 'Remove watermark & stamp annotations', 'default' => true],
                'artifacts' => ['type' => 'toggle', 'label' => 'Remove tagged watermark artifacts', 'default' => true],
                'layers' => ['type' => 'toggle', 'label' => 'Remove watermark layers', 'default' => true],
                'text' => [
                    'type' => 'text',
                    'label' => 'Also remove text matching',
                    'placeholder' => 'e.g. DRAFT (optional)',
                    'max' => 200,
                    'help' => 'Removes text drawing operations that contain exactly this text, where the PDF encoding allows it.',
                ],
            ],
        ],

        'add-page-numbers' => [
            'name' => 'Add Page Numbers',
            'description' => 'Add page numbers to PDF documents with custom position and format.',
            'keywords' => 'add page numbers to pdf, number pdf pages, pdf page numbering',
            'category' => 'editing',
            'also_in' => ['organize'],
            'icon' => 'hash',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'browser' => 'page-numbers',
            'action' => 'Add page numbers',
            'options' => [
                'position' => ['type' => 'position', 'label' => 'Position', 'default' => 'bottom-center', 'rows' => ['top', 'bottom']],
                'format' => [
                    'type' => 'select',
                    'label' => 'Format',
                    'default' => '{n}',
                    'choices' => ['{n}' => '1', 'Page {n}' => 'Page 1', 'Page {n} of {total}' => 'Page 1 of 9', '{n} / {total}' => '1 / 9', '- {n} -' => '- 1 -'],
                ],
                'start' => ['type' => 'number', 'label' => 'First number', 'default' => 1, 'min' => 0, 'max' => 100000],
                'font_size' => ['type' => 'number', 'label' => 'Font size', 'default' => 11, 'min' => 6, 'max' => 72, 'unit' => 'pt'],
                'color' => ['type' => 'color', 'label' => 'Color', 'default' => '#111827'],
                'margin' => ['type' => 'number', 'label' => 'Margin', 'default' => 24, 'min' => 0, 'max' => 200, 'unit' => 'pt'],
                'skip_first' => ['type' => 'toggle', 'label' => 'Do not number the first page', 'default' => false],
            ],
        ],

        'add-header-footer' => [
            'name' => 'Add Header & Footer',
            'description' => 'Add headers and footers with text, dates and page numbers to a PDF.',
            'keywords' => 'add header to pdf, add footer to pdf, pdf header footer',
            'category' => 'editing',
            'icon' => 'header-footer',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'browser' => 'header-footer',
            'action' => 'Add header & footer',
            'notice' => 'Placeholders: {page}, {total}, {date}, {filename}.',
            'options' => [
                'header_left' => ['type' => 'text', 'label' => 'Header – left', 'max' => 200, 'group' => 'header'],
                'header_center' => ['type' => 'text', 'label' => 'Header – center', 'max' => 200, 'group' => 'header'],
                'header_right' => ['type' => 'text', 'label' => 'Header – right', 'max' => 200, 'default' => '{date}', 'group' => 'header'],
                'footer_left' => ['type' => 'text', 'label' => 'Footer – left', 'max' => 200, 'default' => '{filename}', 'group' => 'footer'],
                'footer_center' => ['type' => 'text', 'label' => 'Footer – center', 'max' => 200, 'group' => 'footer'],
                'footer_right' => ['type' => 'text', 'label' => 'Footer – right', 'max' => 200, 'default' => 'Page {page} of {total}', 'group' => 'footer'],
                'font_size' => ['type' => 'number', 'label' => 'Font size', 'default' => 9, 'min' => 5, 'max' => 48, 'unit' => 'pt'],
                'color' => ['type' => 'color', 'label' => 'Color', 'default' => '#374151'],
                'margin' => ['type' => 'number', 'label' => 'Margin', 'default' => 24, 'min' => 0, 'max' => 200, 'unit' => 'pt'],
                'pages' => $pagesField,
            ],
        ],

        /* ------------------------------------------------------------------
         | PDF Organization
         * ------------------------------------------------------------------ */

        'merge-pdf' => [
            'name' => 'Merge PDF',
            'description' => 'Combine multiple PDF files into one document in the order you want.',
            'title' => 'Merge PDF – Combine PDF Files Online for Free',
            'keywords' => 'merge pdf, combine pdf, join pdf files, pdf merger',
            'category' => 'organize',
            'icon' => 'merge',
            'accept' => ['pdf'],
            'multiple' => true,
            'sortable' => true,
            'min_files' => 2,
            'max_files' => 30,
            'engines' => ['browser', 'server'],
            'browser' => 'merge',
            'processor' => Processors\MergeProcessor::class,
            'action' => 'Merge PDF',
            'popular' => true,
            'nav' => true,
            'faq' => [
                ['How many PDFs can I merge?', 'You can combine up to 30 PDF files in one go. Drag the files to put them in the right order before merging.'],
                ['Will the quality of my PDFs change?', 'No. Pages are copied as they are, without re-compressing images or text.'],
            ],
        ],

        'split-pdf' => [
            'name' => 'Split PDF',
            'description' => 'Split a PDF into single pages, page ranges or equal parts.',
            'title' => 'Split PDF – Separate PDF Pages Online for Free',
            'keywords' => 'split pdf, separate pdf pages, divide pdf, pdf splitter',
            'category' => 'organize',
            'icon' => 'split',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'split',
            'processor' => Processors\SplitProcessor::class,
            'action' => 'Split PDF',
            'popular' => true,
            'nav' => true,
            'options' => [
                'mode' => [
                    'type' => 'choice',
                    'label' => 'Split mode',
                    'default' => 'every',
                    'cards' => true,
                    'choices' => [
                        'every' => ['Every page', 'One PDF per page'],
                        'ranges' => ['By page ranges', 'e.g. 1-5, 8-10, 15'],
                        'fixed' => ['Fixed size', 'A new PDF every N pages'],
                        'extract' => ['Extract pages', 'Selected pages into one PDF'],
                    ],
                ],
                'ranges' => [
                    'type' => 'pages',
                    'label' => 'Page ranges',
                    'placeholder' => '1-5, 8-10, 15',
                    'help' => 'Each range becomes its own PDF.',
                    'required_if' => ['mode' => 'ranges'],
                    'show_if' => ['mode' => ['ranges']],
                ],
                'size' => [
                    'type' => 'number',
                    'label' => 'Pages per file',
                    'default' => 2,
                    'min' => 1,
                    'max' => 10000,
                    'show_if' => ['mode' => ['fixed']],
                ],
                'pages' => $pagesField + [
                    'required_if' => ['mode' => 'extract'],
                    'show_if' => ['mode' => ['extract']],
                ],
            ],
            'faq' => [
                ['What do I get when a PDF is split into several files?', 'When more than one PDF is created, all files are packed into a single ZIP archive for one easy download.'],
            ],
        ],

        'extract-pages' => [
            'name' => 'Extract PDF Pages',
            'description' => 'Pick pages and save them as a new PDF document.',
            'keywords' => 'extract pdf pages, save pages from pdf, get pages from pdf',
            'category' => 'organize',
            'icon' => 'file-export',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'page-selection',
            'processor' => Processors\PageSelectionProcessor::class,
            'params' => ['action' => 'extract'],
            'action' => 'Extract pages',
            'options' => ['pages' => ['picker' => true, 'required' => true, 'help' => 'Click pages in the preview or type page numbers.'] + $pagesField],
        ],

        'delete-pages' => [
            'name' => 'Delete PDF Pages',
            'description' => 'Remove unwanted pages from a PDF document.',
            'keywords' => 'delete pdf pages, remove pages from pdf',
            'category' => 'organize',
            'icon' => 'file-minus',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'page-selection',
            'processor' => Processors\PageSelectionProcessor::class,
            'params' => ['action' => 'delete'],
            'action' => 'Delete pages',
            'options' => ['pages' => ['picker' => true, 'required' => true, 'label' => 'Pages to delete', 'help' => 'Click pages in the preview or type page numbers.'] + $pagesField],
        ],

        'organize-pdf' => [
            'name' => 'Organize PDF',
            'description' => 'Manage pages visually: reorder, rotate, delete, duplicate and extract.',
            'keywords' => 'organize pdf, pdf page manager, rearrange pdf pages, sort pdf pages',
            'category' => 'organize',
            'icon' => 'grid',
            'ui' => 'organize',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open page manager',
            'popular' => true,
        ],

        'reorder-pages' => [
            'name' => 'Reorder PDF Pages',
            'description' => 'Drag and drop PDF pages into a new order.',
            'keywords' => 'reorder pdf pages, rearrange pdf, change page order pdf',
            'category' => 'organize',
            'icon' => 'reorder',
            'ui' => 'organize',
            'params' => ['preset' => 'reorder'],
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'action' => 'Open page manager',
        ],

        'rotate-pdf' => [
            'name' => 'Rotate PDF Pages',
            'description' => 'Rotate all or selected pages of a PDF permanently.',
            'keywords' => 'rotate pdf, rotate pdf pages, turn pdf',
            'category' => 'organize',
            'icon' => 'rotate',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'page-selection',
            'processor' => Processors\PageSelectionProcessor::class,
            'params' => ['action' => 'rotate'],
            'action' => 'Rotate PDF',
            'options' => [
                'angle' => [
                    'type' => 'choice',
                    'label' => 'Rotation',
                    'default' => '90',
                    'choices' => ['90' => '90° clockwise', '180' => '180°', '270' => '90° counter-clockwise'],
                ],
                'pages' => ['picker' => true] + $pagesField,
            ],
        ],

        'duplicate-pages' => [
            'name' => 'Duplicate PDF Pages',
            'description' => 'Make copies of selected pages inside a PDF.',
            'keywords' => 'duplicate pdf pages, copy pdf page, repeat page in pdf',
            'category' => 'organize',
            'icon' => 'copy',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'page-selection',
            'processor' => Processors\PageSelectionProcessor::class,
            'params' => ['action' => 'duplicate'],
            'action' => 'Duplicate pages',
            'options' => [
                'pages' => ['picker' => true, 'required' => true, 'label' => 'Pages to duplicate'] + $pagesField,
                'copies' => ['type' => 'number', 'label' => 'Copies of each page', 'default' => 1, 'min' => 1, 'max' => 50],
                'placement' => [
                    'type' => 'choice',
                    'label' => 'Insert copies',
                    'default' => 'after',
                    'choices' => ['after' => 'After each original page', 'end' => 'At the end of the document'],
                ],
            ],
        ],

        /* ------------------------------------------------------------------
         | PDF Compression & Optimization
         * ------------------------------------------------------------------ */

        'compress-pdf' => [
            'name' => 'Compress PDF',
            'description' => 'Reduce PDF file size quickly and easily.',
            'title' => 'Compress PDF – Reduce PDF File Size Online for Free',
            'keywords' => 'compress pdf, reduce pdf size, shrink pdf, pdf compressor, make pdf smaller',
            'category' => 'optimize',
            'icon' => 'compress',
            'accept' => ['pdf'],
            'engines' => ['server', 'browser'],
            'browser' => 'compress',
            'processor' => Processors\CompressProcessor::class,
            'action' => 'Compress PDF',
            'popular' => true,
            'nav' => true,
            'options' => $compressOptions('medium'),
            'faq' => [
                ['How much smaller will my PDF be?', 'It depends on the content. PDFs with many photos or scans often shrink by 50–90%. Text-only PDFs are usually already compact.'],
                ['Will compression reduce the quality?', 'Low compression keeps images almost untouched. Medium is a good balance. High compression gives the smallest file with visibly lower image quality.'],
            ],
        ],

        'optimize-pdf' => [
            'name' => 'Optimize PDF',
            'description' => 'Losslessly optimize PDF structure for faster loading and smaller size.',
            'keywords' => 'optimize pdf, lossless pdf compression, fast web view pdf, linearize pdf',
            'category' => 'optimize',
            'icon' => 'optimize',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'optimize',
            'processor' => Processors\OptimizeProcessor::class,
            'action' => 'Optimize PDF',
            'options' => [
                'remove_metadata' => ['type' => 'toggle', 'label' => 'Remove metadata and thumbnails', 'default' => true],
                'linearize' => ['type' => 'toggle', 'label' => 'Fast web view (linearize, server only)', 'default' => false],
            ],
        ],

        'reduce-pdf-size' => [
            'name' => 'Reduce PDF Size',
            'description' => 'Make a PDF small enough for email and online forms.',
            'keywords' => 'reduce pdf size, pdf size reducer, shrink pdf for email, reduce pdf mb',
            'category' => 'optimize',
            'icon' => 'shrink',
            'accept' => ['pdf'],
            'engines' => ['server', 'browser'],
            'browser' => 'compress',
            'processor' => Processors\CompressProcessor::class,
            'action' => 'Reduce size',
            'options' => $compressOptions('high'),
        ],

        'grayscale-pdf' => [
            'name' => 'Grayscale PDF',
            'description' => 'Convert a color PDF into black and white (grayscale).',
            'keywords' => 'grayscale pdf, pdf to black and white, remove color from pdf',
            'category' => 'optimize',
            'icon' => 'contrast',
            'accept' => ['pdf'],
            'engines' => ['server', 'browser'],
            'browser' => 'grayscale',
            'processor' => Processors\GrayscaleProcessor::class,
            'action' => 'Convert to grayscale',
        ],

        'flatten-pdf' => [
            'name' => 'Flatten PDF',
            'description' => 'Flatten form fields and annotations so they can no longer be edited.',
            'keywords' => 'flatten pdf, flatten pdf form, flatten annotations',
            'category' => 'optimize',
            'icon' => 'flatten',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'flatten',
            'processor' => Processors\FlattenProcessor::class,
            'action' => 'Flatten PDF',
            'options' => [
                'forms' => ['type' => 'toggle', 'label' => 'Flatten form fields', 'default' => true],
                'annotations' => ['type' => 'toggle', 'label' => 'Flatten annotations (comments, stamps, drawings)', 'default' => true],
            ],
        ],

        /* ------------------------------------------------------------------
         | PDF Security
         * ------------------------------------------------------------------ */

        'protect-pdf' => [
            'name' => 'Protect PDF',
            'description' => 'Add a password to your PDF with strong AES-256 encryption.',
            'title' => 'Protect PDF with Password – Free Online PDF Encryption',
            'keywords' => 'protect pdf, password protect pdf, lock pdf, secure pdf',
            'category' => 'security',
            'icon' => 'lock',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'protect',
            'processor' => Processors\ProtectProcessor::class,
            'action' => 'Protect PDF',
            'popular' => true,
            'options' => $protectOptions,
        ],

        'unlock-pdf' => [
            'name' => 'Unlock PDF',
            'description' => 'Remove the password and restrictions from a PDF you have access to.',
            'keywords' => 'unlock pdf, remove pdf password, decrypt pdf, remove pdf restrictions',
            'category' => 'security',
            'icon' => 'unlock',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'unlock',
            'processor' => Processors\UnlockProcessor::class,
            'action' => 'Unlock PDF',
            'notice' => 'Only unlock documents you own or are authorised to use. A PDF that requires a password to open can only be unlocked with that password.',
            'options' => [
                'password' => [
                    'type' => 'password',
                    'label' => 'Current password (if required)',
                    'max' => 128,
                    'help' => 'Leave empty if the PDF opens without a password but has restrictions.',
                ],
            ],
        ],

        'encrypt-pdf' => [
            'name' => 'Encrypt PDF',
            'description' => 'Encrypt PDF documents and set detailed permissions.',
            'keywords' => 'encrypt pdf, pdf encryption, aes pdf, pdf permissions',
            'category' => 'security',
            'icon' => 'shield',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'protect',
            'processor' => Processors\ProtectProcessor::class,
            'action' => 'Encrypt PDF',
            'options' => [
                'algorithm' => [
                    'type' => 'select',
                    'label' => 'Encryption',
                    'default' => 'AES-256',
                    'choices' => ['AES-256' => 'AES 256-bit (recommended)', 'AES-128' => 'AES 128-bit (older readers)'],
                ],
            ] + $protectOptions + [
                'owner_password' => [
                    'type' => 'password',
                    'label' => 'Owner password (optional)',
                    'max' => 128,
                    'help' => 'Required to change permissions. A random one is generated when empty.',
                ],
                'allow_forms' => ['type' => 'toggle', 'label' => 'Allow filling forms', 'default' => true],
                'allow_assembly' => ['type' => 'toggle', 'label' => 'Allow inserting, rotating and deleting pages', 'default' => false],
            ],
        ],

        'remove-pdf-metadata' => [
            'name' => 'Remove PDF Metadata',
            'description' => 'View and remove hidden metadata such as author, title and dates.',
            'keywords' => 'remove pdf metadata, delete pdf properties, clean pdf metadata, pdf privacy',
            'category' => 'security',
            'icon' => 'tag-off',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'browser' => 'metadata',
            'params' => ['mode' => 'remove'],
            'action' => 'Remove metadata',
            'options' => [
                'mode' => [
                    'type' => 'choice',
                    'label' => 'What to do',
                    'default' => 'remove',
                    'choices' => ['remove' => 'Remove all metadata', 'edit' => 'Edit metadata'],
                ],
            ] + ['metadata' => ['type' => 'metadata', 'label' => 'Document properties', 'show_if' => ['mode' => ['edit']]]],
        ],

        'edit-pdf-metadata' => [
            'name' => 'Add PDF Metadata',
            'description' => 'Add or edit the title, author, subject and keywords of a PDF.',
            'keywords' => 'add pdf metadata, edit pdf properties, change pdf title, pdf author',
            'category' => 'security',
            'icon' => 'tag',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'browser' => 'metadata',
            'action' => 'Save metadata',
            'options' => [
                'mode' => ['type' => 'hidden', 'default' => 'edit'],
                'metadata' => ['type' => 'metadata', 'label' => 'Document properties'],
            ],
        ],

        /* ------------------------------------------------------------------
         | OCR & Text
         * ------------------------------------------------------------------ */

        'ocr-pdf' => [
            'name' => 'OCR PDF',
            'description' => 'Make scanned PDFs searchable and extract their text with OCR.',
            'title' => 'OCR PDF – Convert Scanned PDF to Searchable Text',
            'keywords' => 'ocr pdf, scanned pdf to text, searchable pdf, text recognition',
            'category' => 'ocr',
            'icon' => 'scan',
            'accept' => ['pdf'],
            'engines' => ['server', 'browser'],
            'browser' => 'ocr',
            'processor' => Processors\OcrProcessor::class,
            'action' => 'Run OCR',
            'popular' => true,
            'options' => [
                'language' => ['type' => 'ocr-language', 'label' => 'Document language', 'default' => 'eng'],
                'output' => [
                    'type' => 'choice',
                    'label' => 'Output',
                    'default' => 'pdf',
                    'cards' => true,
                    'choices' => [
                        'pdf' => ['Searchable PDF', 'Original pages with an invisible text layer'],
                        'text' => ['Plain text', 'Recognised text as a TXT file'],
                    ],
                ],
                'pages' => $pagesField,
            ],
        ],

        'extract-text-from-pdf' => [
            'name' => 'Extract Text from PDF',
            'description' => 'Copy all text out of a PDF – with OCR for scanned pages.',
            'keywords' => 'extract text from pdf, copy text from pdf, pdf text extractor',
            'category' => 'ocr',
            'icon' => 'text-select',
            'ui' => 'text',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'pdf-to-text',
            'processor' => Processors\PdfToTextProcessor::class,
            'options' => ['layout' => ['type' => 'toggle', 'label' => 'Keep layout', 'default' => false]],
            'action' => 'Extract text',
        ],

        'extract-images-from-pdf' => [
            'name' => 'Extract Images from PDF',
            'description' => 'Save every image embedded in a PDF in its original quality.',
            'keywords' => 'extract images from pdf, save pictures from pdf, pdf image extractor',
            'category' => 'ocr',
            'also_in' => ['image'],
            'icon' => 'images',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'extract-images',
            'processor' => Processors\ExtractImagesProcessor::class,
            'action' => 'Extract images',
            'options' => [
                'min_size' => [
                    'type' => 'number',
                    'label' => 'Skip images smaller than',
                    'default' => 32,
                    'min' => 0,
                    'max' => 2000,
                    'unit' => 'px',
                ],
            ],
        ],

        'search-text-in-pdf' => [
            'name' => 'Search Text in PDF',
            'description' => 'Find every occurrence of a word or phrase in a PDF.',
            'keywords' => 'search pdf, find text in pdf, search inside pdf',
            'category' => 'ocr',
            'icon' => 'search',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'browser' => 'search-text',
            'action' => 'Search',
            'output' => 'inline',
            'options' => [
                'query' => ['type' => 'text', 'label' => 'Search for', 'required' => true, 'max' => 200, 'placeholder' => 'Word or phrase'],
                'case_sensitive' => ['type' => 'toggle', 'label' => 'Match case', 'default' => false],
                'whole_word' => ['type' => 'toggle', 'label' => 'Whole words only', 'default' => false],
            ],
        ],

        'replace-text-in-pdf' => [
            'name' => 'Replace Text in PDF',
            'description' => 'Find text in a PDF and replace it with new text.',
            'keywords' => 'replace text in pdf, find and replace pdf, change words in pdf',
            'category' => 'ocr',
            'also_in' => ['editing'],
            'icon' => 'replace',
            'accept' => ['pdf'],
            'engines' => ['browser'],
            'browser' => 'replace-text',
            'action' => 'Replace text',
            'notice' => 'Matches are covered and the new text is written on top. Works best on simple text on a plain background.',
            'options' => [
                'find' => ['type' => 'text', 'label' => 'Find', 'required' => true, 'max' => 200],
                'replace' => ['type' => 'text', 'label' => 'Replace with', 'max' => 200],
                'case_sensitive' => ['type' => 'toggle', 'label' => 'Match case', 'default' => false],
                'whole_word' => ['type' => 'toggle', 'label' => 'Whole words only', 'default' => false],
            ],
        ],

        /* ------------------------------------------------------------------
         | Translation
         * ------------------------------------------------------------------ */

        'pdf-translator' => [
            'name' => 'PDF Translator',
            'description' => 'Translate PDF documents into 16 languages and download the result.',
            'title' => 'PDF Translator – Translate PDF Documents Online',
            'keywords' => 'pdf translator, translate pdf, pdf translation, translate pdf to english, translate pdf to urdu',
            'category' => 'translation',
            'icon' => 'translate',
            'ui' => 'translator',
            'accept' => ['pdf'],
            'engines' => ['browser', 'server'],
            'browser' => 'pdf-to-text',
            'processor' => Processors\PdfToTextProcessor::class,
            'options' => ['layout' => ['type' => 'toggle', 'label' => 'Keep layout', 'default' => false]],
            'action' => 'Extract & translate',
            'popular' => true,
            'nav' => true,
            'aliases' => ['translate-pdf', 'pdf-translation'],
            'faq' => [
                ['Which languages are supported?', 'English, Urdu, Hindi, Arabic, Chinese, Spanish, French, German, Italian, Portuguese, Russian, Turkish, Bengali, Persian, Indonesian and Malay.'],
                ['Which translation engine is used?', 'Translations are made by open-source engines (LibreTranslate / Argos Translate) running on our own server, or by the on-device translator of your browser. No paid cloud APIs are used.'],
            ],
        ],

        /* ------------------------------------------------------------------
         | Image tools
         * ------------------------------------------------------------------ */

        'compress-image' => [
            'name' => 'Compress Image',
            'description' => 'Reduce the file size of JPG, PNG and WEBP images.',
            'keywords' => 'compress image, reduce image size, image compressor, compress jpg',
            'category' => 'image',
            'icon' => 'image-compress',
            'accept' => ['image'],
            'multiple' => true,
            'max_files' => 30,
            'engines' => ['browser', 'server'],
            'browser' => 'compress-image',
            'processor' => Processors\ImageCompressProcessor::class,
            'action' => 'Compress images',
            'options' => [
                'quality' => ['type' => 'range', 'label' => 'Quality', 'default' => 75, 'min' => 10, 'max' => 95, 'step' => 5, 'unit' => '%'],
                'format' => [
                    'type' => 'select',
                    'label' => 'Output format',
                    'default' => 'keep',
                    'choices' => ['keep' => 'Same as original', 'jpg' => 'JPG', 'webp' => 'WEBP', 'png' => 'PNG'],
                ],
                'max_dimension' => ['type' => 'number', 'label' => 'Limit longest side to', 'default' => 0, 'min' => 0, 'max' => 20000, 'unit' => 'px (0 = keep)'],
            ],
        ],

        'resize-image' => [
            'name' => 'Resize Image',
            'description' => 'Resize images by pixels or percentage.',
            'keywords' => 'resize image, change image size, image resizer, scale image',
            'category' => 'image',
            'icon' => 'image-resize',
            'accept' => ['image'],
            'multiple' => true,
            'max_files' => 30,
            'engines' => ['browser', 'server'],
            'browser' => 'resize-image',
            'processor' => Processors\ImageResizeProcessor::class,
            'action' => 'Resize images',
            'options' => [
                'mode' => [
                    'type' => 'choice',
                    'label' => 'Resize by',
                    'default' => 'percent',
                    'choices' => ['percent' => 'Percentage', 'pixels' => 'Pixels'],
                ],
                'percent' => ['type' => 'range', 'label' => 'Scale', 'default' => 50, 'min' => 1, 'max' => 400, 'unit' => '%', 'show_if' => ['mode' => ['percent']]],
                'width' => ['type' => 'number', 'label' => 'Width', 'default' => 1200, 'min' => 1, 'max' => 20000, 'unit' => 'px', 'show_if' => ['mode' => ['pixels']]],
                'height' => ['type' => 'number', 'label' => 'Height', 'default' => 0, 'min' => 0, 'max' => 20000, 'unit' => 'px (0 = auto)', 'show_if' => ['mode' => ['pixels']]],
                'keep_ratio' => ['type' => 'toggle', 'label' => 'Keep aspect ratio', 'default' => true, 'show_if' => ['mode' => ['pixels']]],
                'format' => [
                    'type' => 'select',
                    'label' => 'Output format',
                    'default' => 'keep',
                    'choices' => ['keep' => 'Same as original', 'jpg' => 'JPG', 'png' => 'PNG', 'webp' => 'WEBP'],
                ],
            ],
        ],
    ],
];
