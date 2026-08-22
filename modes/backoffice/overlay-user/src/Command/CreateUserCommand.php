<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

/**
 * Crée un compte depuis la console — le premier administrateur, typiquement, puisque l'inscription
 * publique crée des comptes inactifs.
 *
 * ⚠️ Le mot de passe est demandé en saisie MASQUÉE, pas pris en argument : un argument de commande
 * atterrit dans l'historique du shell, dans `ps`, et dans les journaux de l'orchestrateur.
 *
 * Pas de verrou Symfony ici : la commande n'est jamais planifiée, elle est lancée à la main.
 */
#[AsCommand(name: 'app:user:create', description: 'Crée un compte')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse du compte')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Donne ROLE_SUPER_ADMIN')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Prénom', '')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Nom', '');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if (null !== $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)])) {
            $io->error(sprintf('Un compte existe déjà pour « %s ».', $email));

            return Command::FAILURE;
        }

        $password = (string) $io->askHidden('Mot de passe');
        if ('' === $password) {
            $io->error('Mot de passe vide.');

            return Command::FAILURE;
        }

        $user = new User()
            ->setEmail($email)
            ->setFirstName((string) $input->getOption('first-name'))
            ->setLastName((string) $input->getOption('last-name'))
            ->setIsActive(true);

        if (true === $input->getOption('admin')) {
            $user->setRoles([UserRoles::ROLE_SUPER_ADMIN]);
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Compte « %s » créé.', $email));

        return Command::SUCCESS;
    }
}
