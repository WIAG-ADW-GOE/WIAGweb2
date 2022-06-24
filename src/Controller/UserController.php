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


class UserController extends AbstractController
{
    /**
     * @Route("/user/edit/{email}", name="user_edit")
     */
    public function edit(Request $request,
                         UserPasswordHasherInterface $userPasswordHasher,
                         EntityManagerInterface $entityManager,
                         $email = null): Response {

        if (is_null($email)) {
            $user = new UserWiag();
        } else {
            $userRepository = $entityManager->getRepository(UserWiag::class);
            $user = $this->userRepository->findOneBy(['email' => $email]);
        }

        $form = $this->createForm(UserFormType::class, $user);
        $form->handleRequest($request);
        $message = $request->query->get('message');

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO get the user object as active_user
            $active_user = $this->getUser();
            $active_user_id = $active_user ? $active_user->getId() : 1;
            $user->setCreatedBy($active_user_id);
            $now = new \DateTime("now");
            $user->setDateCreated($now);
            $user->setChangedBy($active_user_id);
            $user->setDateChanged($now);

            // encode the plain password
            $user->setPassword(
            $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setActive(1);

            // $role = $form->get('roleChoice')->getData();
            // $user->setRoles([$role]);

            $entityManager->persist($user);
            $entityManager->flush();
            // do anything else you need here, like send an email

            // alternative: log in and redirect as in the login process (see SymfonyCast tutorial)

            $message = $user->getName().' ist eingetragen';
            return $this->redirectToRoute('user_edit', [
                'message' => $user->getName().' ist eingetragen'
            ]);


            $user = new UserWiag();
            $form = $this->createForm(UserFormType::class, $user);

            // return $this->redirectToRoute('home');
        }

        return $this->render('user/user.html.twig', [
            'userForm' => $form->createView(),
            'message' => $message,
        ]);
    }

    /**
     * @Route("/user/list", name="user_list")
     */
    public function list(Request $request,
                         EntityManagerInterface $entityManager): Response {

        return new Response('user list');
    }
}
