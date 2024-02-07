<?php

namespace App\Controller;

use App\Entity\UserWiag;
use App\Form\RegistrationFormType;
use App\Service\PersonService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="user_register")
     * @IsGranted("ROLE_EDIT_USER")
     */
    public function register(Request $request,
                             UserPasswordHasherInterface $userPasswordHasher,
                             EntityManagerInterface $entityManager,
                             UserAuthenticatorInterface $userAuthenticator,
                             FormLoginAuthenticator $formLoginAuthenticator): Response {
        $user = new UserWiag();
        $has_admin_access = $this->isGranted("ROLE_ADMIN");

        $role_list = array_merge(PersonService::roleEditList($entityManager), UserWiag::ROLE_LIST);

        // admin users may grant special rights
        if ($has_admin_access) {
            $role_list = array_merge($role_list, UserWiag::ROLE_EXTRA_LIST);
        }

        $has_admin_access = $this->isGranted("ROLE_ADMIN");
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'role_list' => $role_list
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $now = new \DateTime("now");
            $user_current = $this->getUser();

            $user->setCreatedBy($user_current->getId());
            $user->setDateCreated($now);
            $user->setChangedBy($user_current->getId());
            $user->setDateChanged($now);

            $user->setActive(1);
            // encode the plain password
            $user->setPassword(
            $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setNameByEmail($user->getEmail());

            $entityManager->persist($user);
            $entityManager->flush();
            // do anything else you need here, like send an email

            // see https://symfonycasts.com/screencast/symfony-security
            // return $userAuthenticator->authenticateUser(
            //     $user,
            //     $formLoginAuthenticator,
            //     $request
            // );
            return $this->render('registration/register_success.html.twig', [
                'menuItem' => 'edit-menu',
                'user' => $user,
            ]);

        }

        return $this->render('registration/register.html.twig', [
            'menuItem' => 'edit-menu',
            'registrationForm' => $form->createView(),
        ]);
    }
}
