<?php

namespace App\Controller;

use App\Entity\UserWiag;
use App\Form\UserFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


class UserController extends AbstractController
{
    /**
     * @Route("/user/edit/{email}", name="user_edit")
     * @IsGranted("ROLE_USER_EDIT")
     */
    public function edit(Request $request,
                         UserPasswordHasherInterface $userPasswordHasher,
                         EntityManagerInterface $entityManager,
                         $email = null): Response {

        $userRepository = $entityManager->getRepository(UserWiag::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        $has_admin_access = $this->isGranted("ROLE_ADMIN");
        // dump($has_admin_access);
        $form = $this->createForm(UserFormType::class, $user, [
            'has_admin_access' => $has_admin_access
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

            return $this->redirectToRoute('user_list');

        }

        return $this->render('user/user_edit.html.twig', [
            'menuItem' => 'edit',
            'userForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/user/list", name="user_list")
     */
    public function list(Request $request,
                         EntityManagerInterface $entityManager): Response {
        $repository = $entityManager->getRepository(UserWiag::class);
        $user_list = $repository->findBy([], ['id' => 'ASC']);

        return $this->render('user/user_list.html.twig', [
            'menuItem' => 'edit',
            'user_list' => $user_list,
        ]);
    }
}
