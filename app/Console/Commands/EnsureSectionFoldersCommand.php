<?php

namespace App\Console\Commands;

use App\Services\SectionFolderService;
use Illuminate\Console\Command;

class EnsureSectionFoldersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sections:ensure-folders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure section folders (activities, quiz, report, project) exist for all sections';

    /**
     * Execute the console command.
     */
    public function handle(SectionFolderService $service): int
    {
        $this->info('Ensuring section folders structure...');
        $count = $service->ensureAllSectionFolders();
        $this->info("Successfully ensured folders for {$count} sections.");

        return self::SUCCESS;
    }
}
