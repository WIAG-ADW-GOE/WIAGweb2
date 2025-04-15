<?php

namespace App\Controller;

use App\Entity\UserWiag;
use App\Form\UserFormType;
use App\Service\PersonService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


class UserController extends AbstractController
{

    #[Route(path: '/user/edit/{email}', name: 'edit_user')]
    #[IsGranted('ROLE_EDIT_USER')]
    public function edit(Request $request,
                         UserPasswordHasherInterface $userPasswordHasher,
                         EntityManagerInterface $entityManager,
                         $email = null): Response {

        $userRepository = $entityManager->getRepository(UserWiag::class);
        $user = $userRepository->findOneBy(['email' => $email]);
        $has_admin_access = $this->isGranted("ROLE_ADMIN");

        $role_list = array_merge(PersonService::roleEditList($entityManager), UserWiag::ROLE_LIST);

        // admin users may grant special rights
        if ($has_admin_access) {
            $role_list = array_merge($role_list, UserWiag::ROLE_EXTRA_LIST);
        }

        $form = $this->createForm(UserFormType::class, $user, [
            'role_list' => $role_list,
        ]);
        $form->handleRequest($request);
        $message = $request->query->get('message');

        if ($form->isSubmitted() && $form->isValid()) {
            $active_user = $this->getUser();
            $active_user_id = $active_user ? $active_user->getId() : 1;

            $now = new \DateTime("now");
            $user->setChangedBy($active_user_id);
            $user->setDateChanged($now);
            $plainPassword = $form->get('plainPassword');

            // encode the plain password (if a new value was entered)
            if ($plainPassword->getData()) {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );
            }

            $user->setNameByEmail($user->getEmail());
            $user->setActive(1);

            $entityManager->persist($user);
            $entityManager->flush();
            // do anything else you need here, like send an email


            // alternative: log in and redirect as in the login process (see SymfonyCast tutorial)
            return $this->redirectToRoute('user_list', [
                'roleNameList' => array_flip($role_list)
            ]);

        }

        return $this->render('user/user_edit.html.twig', [
            'menuItem' => 'edit-menu',
            'userForm' => $form->createView(),
        ]);
    }

    #[Route(path: '/user/list', name: 'user_list')]
    public function list(Request $request,
                         EntityManagerInterface $entityManager): Response {
        $repository = $entityManager->getRepository(UserWiag::class);
        $user_list = $repository->findBy([], ['id' => 'ASC']);

        $role_list = array_merge(PersonService::roleEditList($entityManager),
                                 UserWiag::ROLE_LIST,
                                 UserWiag::ROLE_EXTRA_LIST);

        return $this->render('user/user_list.html.twig', [
            'roleNameList' => array_flip($role_list),
            'menuItem' => 'edit-menu',
            'user_list' => $user_list,
        ]);
    }

}
