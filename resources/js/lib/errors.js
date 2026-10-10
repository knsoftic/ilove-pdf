/**
 * User-friendly errors. Mirrors App\Exceptions\ToolException on the server.
 */
export const MESSAGES = {
    invalid_pdf: 'This file is not a valid PDF document.',
    corrupted_pdf: 'This PDF appears to be damaged or corrupted and cannot be processed.',
    password_protected: 'This PDF is password protected. Please unlock it first or enter the password.',
    wrong_password: 'The password you entered is incorrect.',
    file_too_large: 'This file is too large. Please upload a smaller file.',
    unsupported_format: 'This file format is not supported by this tool.',
    too_many_files: 'Too many files. Please upload fewer files at once.',
    too_few_files: 'Please add more files to use this tool.',
    no_file: 'Please choose a file to process.',
    invalid_page_range: 'The page selection is not valid for this document.',
    invalid_options: 'Some of the options are not valid.',
    ocr_failed: 'Text recognition (OCR) failed for this document.',
    conversion_failed: 'The conversion failed. The file may be damaged or use unsupported features.',
    translation_failed: 'The translation failed. Please try again in a moment.',
    translation_unavailable: 'No translation engine is available.',
    engine_unavailable: 'This tool is not available right now.',
    nothing_found: 'Nothing was found to process in this document.',
    rate_limited: 'Too many requests. Please wait a minute and try again.',
    job_not_found: 'This file has expired or was already deleted. Please process it again.',
    out_of_memory: 'This file is too large to be processed in your browser. Try the server option or a smaller file.',
    network: 'Network error. Please check your connection and try again.',
    cancelled: 'Cancelled.',
    server_error: 'Something went wrong while processing your file. Please try again.',
};

export const TITLES = {
    invalid_pdf: 'Invalid PDF',
    corrupted_pdf: 'PDF is corrupted',
    password_protected: 'Password protected PDF',
    wrong_password: 'Wrong password',
    file_too_large: 'File too large',
    unsupported_format: 'Unsupported format',
    ocr_failed: 'OCR failed',
    conversion_failed: 'Conversion failed',
    translation_failed: 'Translation failed',
    server_error: 'Server processing error',
    rate_limited: 'Slow down',
};

export class ToolError extends Error {
    constructor(code, message) {
        super(message || MESSAGES[code] || MESSAGES.server_error);
        this.name = 'ToolError';
        this.code = code;
    }

    get title() {
        return TITLES[this.code] || 'Something went wrong';
    }
}

/**
 * Converts any exception (pdf.js, pdf-lib, network, ...) into a ToolError.
 */
export function toToolError(error) {
    if (error instanceof ToolError) return error;

    const name = error?.name || '';
    const message = String(error?.message || '');

    if (name === 'AbortError') return new ToolError('cancelled');
    if (name === 'PasswordException') return new ToolError(error.code === 2 ? 'wrong_password' : 'password_protected');
    if (name === 'EncryptedPDFError' || /encrypted|NEEDS PASSWORD/i.test(message)) return new ToolError('password_protected');
    if (/Password incorrect/i.test(message)) return new ToolError('wrong_password');
    if (name === 'InvalidPDFException' || /Invalid PDF|No PDF header|Failed to parse PDF/i.test(message)) return new ToolError('invalid_pdf');
    if (/MissingPDF|corrupt|xref|trailer|Unexpected|PDFParsingError|Expected instance/i.test(message)) return new ToolError('corrupted_pdf');
    if (name === 'RangeError' && /allocation|memory|Array buffer/i.test(message)) return new ToolError('out_of_memory');
    if (/out of memory/i.test(message)) return new ToolError('out_of_memory');

    console.error(error);
    return new ToolError('server_error');
}
