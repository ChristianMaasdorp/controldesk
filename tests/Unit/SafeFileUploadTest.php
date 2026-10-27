<?php

namespace Tests\Unit;

use App\Helpers\SafeFileUpload;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;
use Tests\TestCase;

class SafeFileUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
    }

    /** @dataProvider rejectedNames */
    public function test_dangerous_files_are_rejected(string $name): void
    {
        $upload = $this->makeUpload(SafeFileUpload::attachments());

        $this->assertNotNull($this->validationError($upload, $this->tempFile($name)));

        $this->expectException(ValidationException::class);
        $upload->getUploadedFileNameForStorage($this->tempFile($name));
    }

    public static function rejectedNames(): array
    {
        return [
            'php' => ['shell.php'],
            'uppercase php' => ['shell.PHP'],
            'php5' => ['shell.php5'],
            'phtml' => ['shell.phtml'],
            'phar' => ['shell.phar'],
            'double extension' => ['shell.php.jpg'],
            'htaccess' => ['.htaccess'],
            'html' => ['page.html'],
            'svg' => ['image.svg'],
            'no extension' => ['README'],
            'not allowed' => ['tool.exe'],
        ];
    }

    public function test_allowed_files_are_stored_under_a_generated_name(): void
    {
        $upload = $this->makeUpload(SafeFileUpload::attachments());
        $file = $this->tempFile('Invoice March.PDF');

        $this->assertNull($this->validationError($upload, $file));

        $stored = $upload->getUploadedFileNameForStorage($file);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.pdf$/', $stored);
    }

    public function test_preserved_filenames_are_slugged(): void
    {
        $upload = $this->makeUpload(['pdf'])->preserveFilenames();

        $stored = $upload->getUploadedFileNameForStorage($this->tempFile('BRS v2 (final).pdf'));

        $this->assertSame('brs-v2-final.pdf', $stored);
    }

    public function test_extension_list_is_specific_to_the_field(): void
    {
        $upload = $this->makeUpload(SafeFileUpload::IMAGES);

        $this->assertNotNull($this->validationError($upload, $this->tempFile('report.pdf')));
        $this->assertNull($this->validationError($upload, $this->tempFile('logo.png')));
    }

    private function makeUpload(array $extensions): FileUpload
    {
        $upload = SafeFileUpload::apply(FileUpload::make('file'), $extensions);

        $upload->container(
            ComponentContainer::make(new SafeFileUploadTestForm())->statePath('data')
        );

        return $upload;
    }

    private function tempFile(string $originalName): TemporaryUploadedFile
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = Str::random(30) . str('-meta' . base64_encode($originalName) . '-')->replace('/', '_') . '.' . $extension;

        Storage::disk(config('filesystems.default'))->put('livewire-tmp/' . $name, 'test');

        return new TemporaryUploadedFile($name, config('filesystems.default'));
    }

    private function validationError(FileUpload $upload, TemporaryUploadedFile $file): ?string
    {
        $error = null;

        foreach ($upload->getValidationRules() as $rule) {
            if ($rule instanceof \Closure) {
                $rule('data.file', [$file], function ($message) use (&$error) {
                    $error = $message;
                });
            }
        }

        return $error;
    }
}

class SafeFileUploadTestForm extends Component implements HasForms
{
    use InteractsWithForms;
}
