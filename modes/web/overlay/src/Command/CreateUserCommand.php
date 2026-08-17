<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function count;
use function is_string;
use function sprintf;
use function strlen;

/**
 * Crée un compte en ligne de commande — notamment le premier administrateur,
 * puisque l'inscription publique ne donne que ROLE_USER.
 */
#[AsCommand(name: 'app:user:create', description: 'Crée un compte utilisateur')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail du compte')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe (demandé si absent)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Donne le rôle ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');

        if (null !== $this->users->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un compte existe déjà avec l\'adresse « %s ».', $email));

            return Command::FAILURE;
        }

        $password = $input->getArgument('password');
        if (!is_string($password) || '' === $password) {
            /** @var string $password */
            $password = $io->askHidden('Mot de passe', static function (?string $value): string {
                if (null === $value || strlen($value) < 8) {
                    throw new RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
                }

                return $value;
            });
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(true === $input->getOption('admin') ? [User::ROLE_ADMIN] : []);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $violations = $this->validator->validate($user);
        if (0 !== count($violations)) {
            foreach ($violations as $violation) {
                $io->error(sprintf('%s : %s', $violation->getPropertyPath(), (string) $violation->getMessage()));
            }

            return Command::INVALID;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Compte « %s » créé (%s).', $email, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
