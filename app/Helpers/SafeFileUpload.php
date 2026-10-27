<?php

namespace App\Helpers;

use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\TemporaryUploadedFile;

/**
 * Locks down Filament file uploads.
 *
 * Uploads stored on the public disk are reachable under /storage, so a file
 * that keeps a ".php" extension can be executed by the web server. The MIME
 * check done by acceptedFileTypes() sniffs content and is fooled by polyglot
 * files (e.g. a GIF header followed by PHP code), so we also:
 *  - reject any file whose client extension is not explicitly allowed;
 *  - store every file under a generated name with a normalised, allowed
 *    extension, never the name chosen by the client.
 */
class SafeFileUpload
{
    public const IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public const DOCUMENTS = [
        'pdf', 'txt', 'csv',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'rtf',
    ];

    public const SPREADSHEETS = ['csv', 'xls', 'xlsx'];

    public const ARCHIVES = ['zip'];

    public const EMAILS = ['eml', 'msg'];

    public static function attachments(): array
    {
        return array_merge(self::IMAGES, self::DOCUMENTS, self::ARCHIVES, self::EMAILS);
    }

    /**
     * @template T of BaseFileUpload
     * @param T $component
     * @return T
     */
    public static function apply(BaseFileUpload $component, array $extensions): BaseFileUpload
    {
        $extensions = array_map('strtolower', $extensions);

        return $component
            ->rule(static fn () => static function (string $attribute, $value, \Closure $fail) use ($extensions): void {
                if (! $value instanceof TemporaryUploadedFile) {
                    return;
                }

                if (self::extensionOf($value, $extensions) === null) {
                    $fail(__('This file type is not allowed. Allowed types: :types', [
                        'types' => implode(', ', $extensions),
                    ]));
                }
            })
            ->getUploadedFileNameForStorageUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($extensions): string {
                $extension = self::extensionOf($file, $extensions);

                if ($extension === null) {
                    throw ValidationException::withMessages([
                        $component->getStatePath() => __('This file type is not allowed.'),
                    ]);
                }

                $basename = $component->shouldPreserveFilenames()
                    ? Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    : '';

                return ($basename !== '' ? $basename : Str::random(40)) . '.' . $extension;
            });
    }

    /**
     * Returns the file's extension if it is allowed, null otherwise.
     * Names such as "shell.php.jpg" are rejected outright, as some server
     * configurations execute files based on an inner extension.
     */
    private static function extensionOf(TemporaryUploadedFile $file, array $extensions): ?string
    {
        $name = strtolower($file->getClientOriginalName());

        if (preg_match('/\.(php\d*|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp|htaccess|htm|html|svg|js)(\.|$)/', $name)) {
            return null;
        }

        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($extension, $extensions, true) ? $extension : null;
    }
}
