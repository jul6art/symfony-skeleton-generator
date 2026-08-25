<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

use function sprintf;

/**
 * Fabrique une URL de connexion à usage unique, pour un compte donné.
 *
 * Sert à ouvrir une session sur la stack locale **sans saisir de mot de passe** — pour regarder les
 * écrans tourner avec n'importe quel compte de fixtures, y compris ceux d'une autre organisation.
 * L'URL est signée et expire ; elle ne remplace pas la connexion, elle évite d'avoir à taper des
 * identifiants.
 *
 * ```
 * symfony console app:dev:login-link manager@example.test
 * ```
 *
 * ⚠️ `#[When('dev')]` : la commande n'est enregistrée qu'en développement. Sans cet attribut, elle
 * exigerait `LoginLinkHandlerInterface` — un service qui n'existe que là où `login_link` est
 * configuré — et le conteneur de production refuserait de compiler. L'attribut dit donc deux choses
 * à la fois : ce n'est pas un outil de production, et ça ne peut pas l'être.
 */
#[When('dev')]
#[AsCommand(name: 'app:dev:login-link', description: 'Fabrique une URL de connexion sans mot de passe (développement uniquement)')]
final class DevLoginLinkCommand extends Command
{
    public function __construct(
        // ⚠️ Le service est nommé, pas autowiré par son interface : Symfony fabrique UN
        // `LoginLinkHandler` PAR PARE-FEU, donc `LoginLinkHandlerInterface` est ambigu et
        // l'injection échoue avec « cannot be determined ». C'est celui du pare-feu `main`, le seul
        // qui porte `login_link`.
        #[Autowire(service: 'security.authenticator.login_link_handler.main')]
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'L\'adresse du compte à ouvrir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $user = $this->users->findOneBy(['email' => $email]);

        if (null === $user) {
            $style->error(sprintf('Aucun compte avec l\'adresse « %s ».', $email));

            return Command::FAILURE;
        }

        $details = $this->loginLinkHandler->createLoginLink($user);

        // L'URL seule sur la sortie : elle se copie, se passe à `curl`, s'ouvre dans un navigateur.
        // Les commentaires vont sur STDERR via SymfonyStyle, ce qui garde `$(…)` utilisable.
        $style->success(sprintf('Lien pour %s (expire selon security.yaml) :', $email));
        $output->writeln($details->getUrl());

        return Command::SUCCESS;
    }
}
