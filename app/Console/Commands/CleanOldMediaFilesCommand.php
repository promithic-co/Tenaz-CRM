<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOldMediaFilesCommand extends Command
{
    protected $signature = 'credflow:clean-media {--days=7 : Arquivos mais antigos que N dias serão removidos}';

    protected $description = 'Remove arquivos de mídia (áudios e imagens) processados há mais de N dias';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days)->getTimestamp();
        $mediaDir = 'media';

        if (! Storage::exists($mediaDir)) {
            $this->info('Diretório de mídia não existe ainda. Nada a limpar.');

            return self::SUCCESS;
        }

        $files = Storage::allFiles($mediaDir);
        $removed = 0;
        $errors = 0;

        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);

            if ($lastModified < $threshold) {
                try {
                    Storage::delete($file);
                    $removed++;
                } catch (\Throwable $e) {
                    $this->warn("Erro ao remover {$file}: {$e->getMessage()}");
                    $errors++;
                }
            }
        }

        // Remover sub-diretórios vazios
        $this->removeEmptyDirectories($mediaDir);

        $this->info("Limpeza concluída: {$removed} arquivo(s) removido(s), {$errors} erro(s).");

        return self::SUCCESS;
    }

    private function removeEmptyDirectories(string $dir): void
    {
        $directories = Storage::allDirectories($dir);

        // Processar do mais profundo para o mais raso
        foreach (array_reverse($directories) as $directory) {
            $files = Storage::allFiles($directory);
            if (empty($files)) {
                Storage::deleteDirectory($directory);
            }
        }
    }
}
