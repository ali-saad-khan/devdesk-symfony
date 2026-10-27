<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SlaChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:tickets:check-sla', description: 'Notify agents about tickets that missed their first-response deadline')]
final class CheckSlaCommand extends Command
{
    public function __construct(private readonly SlaChecker $checker)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->checker->run();
        (new SymfonyStyle($input, $output))->success(\sprintf('%d ticket(s) breached their SLA.', $count));

        return Command::SUCCESS;
    }
}
