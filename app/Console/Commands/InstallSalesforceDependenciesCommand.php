<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class InstallSalesforceDependenciesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesforce:install-dependencies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install required Node.js packages (jsforce) for Salesforce synchronization';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('📦 Installing Salesforce Node.js Dependencies...');
        $this->info('Project Path: ' . base_path());
        $this->info('====================================================');

        // Check node and npm
        $nodeCheck = new Process(['node', '-v'], base_path());
        $nodeCheck->run();
        if (!$nodeCheck->isSuccessful()) {
            $this->error('❌ Node.js is not found in the server PATH.');
            return Command::FAILURE;
        }
        $this->line('Found Node.js: ' . trim($nodeCheck->getOutput()));

        $npmCheck = new Process(['npm', '-v'], base_path());
        $npmCheck->run();
        if (!$npmCheck->isSuccessful()) {
            $this->error('❌ npm is not found in the server PATH.');
            return Command::FAILURE;
        }
        $this->line('Found npm: ' . trim($npmCheck->getOutput()));

        $this->info('Running: npm install --omit=dev ...');
        $npmInstall = new Process(['npm', 'install', '--omit=dev'], base_path(), null, null, 300);
        $npmInstall->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if ($npmInstall->isSuccessful()) {
            $this->newLine();
            $this->info('✅ Node.js dependencies installed successfully!');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->warn('Standard npm install failed, trying fallback: npm install jsforce ...');

        $fallbackInstall = new Process(['npm', 'install', 'jsforce'], base_path(), null, null, 300);
        $fallbackInstall->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if ($fallbackInstall->isSuccessful()) {
            $this->newLine();
            $this->info('✅ jsforce installed successfully via fallback!');
            return Command::SUCCESS;
        }

        $this->error('❌ Failed to install npm dependencies: ' . $fallbackInstall->getErrorOutput());
        return Command::FAILURE;
    }
}
