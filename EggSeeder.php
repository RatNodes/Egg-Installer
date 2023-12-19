<?php

namespace Database\Seeders;

use Jexactyl\Models\Nest;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Jexactyl\Services\Eggs\Sharing\EggImporterService;
use Jexactyl\Contracts\Repository\EggRepositoryInterface;
use Jexactyl\Contracts\Repository\NestRepositoryInterface;
use Jexactyl\Exceptions\Repository\RecordNotFoundException;
use Jexactyl\Services\Eggs\Sharing\EggUpdateImporterService;

class EggSeeder extends Seeder
{
    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $filesystem;

    /**
     * @var \Jexactyl\Services\Eggs\Sharing\EggImporterService
     */
    private $importerService;

    /**
     * @var \Jexactyl\Contracts\Repository\NestRepositoryInterface
     */
    private $nestRepository;

    /**
     * @var \Jexactyl\Contracts\Repository\EggRepositoryInterface
     */
    private $repository;

    /**
     * @var \Jexactyl\Services\Eggs\Sharing\EggUpdateImporterService
     */
    private $updateImporterService;

    /**
     * EggSeeder constructor.
     */
    public function __construct(
        EggImporterService $importerService,
        EggRepositoryInterface $repository,
        EggUpdateImporterService $updateImporterService,
        Filesystem $filesystem,
        NestRepositoryInterface $nestRepository
    ) {
        $this->filesystem = $filesystem;
        $this->importerService = $importerService;
        $this->repository = $repository;
        $this->updateImporterService = $updateImporterService;
        $this->nestRepository = $nestRepository;
    }

    /**
     * Run the egg seeder.
     */
    public function run()
    {
        $this->getEggsToImport()->each(function ($nest) {
            $this->parseEggFiles($this->findMatchingNest($nest));
        });
    }

    /**
     * Return a list of eggs to import.
     */
    protected function getEggsToImport(): Collection
    {
        return collect([
            'Source Engine',
            'Voice Servers',
            'Rust',
            'Among Us',
            'BeamMP',
            'BeamNG',
            'Bots',
            'Call of Duty',
            'CryoFall',
            'Database',
            'Enemy Territory',
            'Factorio',
            'Faster Than Light',
            'Grand Theft Auto',
            'League Sandbox',
            'Mindustry',
            'Minetest',
            'Openarena',
            'OpenRA',
            'Red Dead Redemption',
            'Software',
            'StarMade',
            'Storage',
            'Teeworlds',
            'Terraria',
            'Tycoon Games',
            'Unreal Engine',
            'Veloren',
            'Vintage Story',
            'Xonotic',
        ]);
    }

    /**
     * Find the nest that these eggs should be attached to.
     *
     * @throws \Jexactyl\Exceptions\Repository\RecordNotFoundException
     */
    private function findMatchingNest(string $nestName): Nest
    {
        return $this->nestRepository->findFirstWhere([
            ['author', '=', 'support@Jexactyl.io'],
            ['name', '=', $nestName],
        ]);
    }

    /**
     * Loop through the list of egg files and import them.
     */
    private function parseEggFiles(Nest $nest)
    {
        $files = $this->filesystem->allFiles(database_path('Seeders/eggs/' . kebab_case($nest->name)));

        $this->command->alert('Updating Eggs for Nest: ' . $nest->name);
        Collection::make($files)->each(function ($file) use ($nest) {
            /* @var \Symfony\Component\Finder\SplFileInfo $file */
            $decoded = json_decode($file->getContents());
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->command->error('JSON decode exception for ' . $file->getFilename() . ': ' . json_last_error_msg());

                return;
            }

            $file = new UploadedFile($file->getPathname(), $file->getFilename(), 'application/json');

            try {
                $egg = $this->repository->setColumns('id')->findFirstWhere([
                    ['author', '=', $decoded->author],
                    ['name', '=', $decoded->name],
                    ['nest_id', '=', $nest->id],
                ]);

                $this->updateImporterService->handle($egg, $file);

                $this->command->info('Updated ' . $decoded->name);
            } catch (RecordNotFoundException $exception) {
                $this->importerService->handle($file, $nest->id);

                $this->command->comment('Created ' . $decoded->name);
            }
        });

        $this->command->line('');
    }
}
