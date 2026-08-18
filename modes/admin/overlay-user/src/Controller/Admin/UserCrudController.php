<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Event\UserEvent;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * CRUD des comptes.
 *
 * @extends AbstractCrudController<User>
 *
 * Le mot de passe est saisi en clair dans `plainPassword` (non persisté) puis
 * haché ici : l'entité ne voit jamais passer un mot de passe en clair en base.
 */
final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserManagerInterface $userManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.users.singular')
            ->setEntityLabelInPlural('admin.users.title')
            ->setDefaultSort(['email' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, User::ROLE_ADMIN);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield EmailField::new('email', 'user.email');

        yield ChoiceField::new('roles', 'user.roles')
            ->setChoices(['user.role.admin' => User::ROLE_ADMIN])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setHelp('user.roles_help');

        yield TextField::new('plainPassword', 'user.password')
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('always_empty', true)
            ->onlyOnForms()
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->setHelp(Crud::PAGE_NEW === $pageName ? '' : 'user.password_keep');
    }

    /**
     * @param User $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        $this->userManager->save($entityInstance);
        $this->eventDispatcher->dispatch(new UserEvent($entityInstance), UserEvent::CREATED);
    }

    /**
     * @param User $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        $this->userManager->save($entityInstance);
        $this->eventDispatcher->dispatch(new UserEvent($entityInstance), UserEvent::EDITED);
    }

    /**
     * @param User $entityInstance
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->eventDispatcher->dispatch(new UserEvent($entityInstance), UserEvent::DELETED);
        $this->userManager->delete($entityInstance);
    }

    private function hashPassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();

        if (null === $plainPassword || '' === $plainPassword) {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->eraseCredentials();
    }
}
