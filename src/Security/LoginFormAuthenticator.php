<?php

namespace App\Security;

use App\Entity\UserWiag;
use App\Repository\UserWiagRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * custom authenticator see https://symfonycasts.com/screencast/symfony-security/abstract-login-form-authenticator
 */
class LoginFormAuthenticator extends AbstractLoginFormAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    private UserWiagRepository $userRepository;
    private RouterInterface $router;
    private ContainerBagInterface $params;

    public function __construct(UserWiagRepository $userRepository,
                                RouterInterface $router,
                                ContainerBagInterface $params) {
        $this->userRepository = $userRepository;
        $this->router = $router;
        $this->params = $params;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email');
        $plainPassword = $request->request->get('password');
        return new Passport(
            new UserBadge($email, function($userIdentifier){
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new UserNotFoundException();
                }

                return $user;
            }),
            new PasswordCredentials($plainPassword),
            [
                new CsrfTokenBadge(
                    'authenticate',
                    $request->request->get('_csrf_token')
                ),
                new RememberMeBadge(),
            ]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $target = $this->getTargetPath($request->getSession(), $firewallName);
        if ($target) {
            // the server (GWDG-hosting) fails to inform Symfony about the current scheme
            if ($this->params->get('app.env') == 'prod') {
                $target = str_replace('http://', 'https://', $target);
            }
            return new RedirectResponse($target);
        }

        // $target = $this->router->generate('home');
        // the server (GWDG-hosting) fails to inform Symfony about the current scheme
        $target = $request->getSchemeAndHttpHost().$this->router->generate('home');
        if ($this->params->get('app.env') == 'prod') {
            $target = str_replace('http://', 'https://', $target);
        }
        return new RedirectResponse($target);
    }

    protected function getLoginUrl(Request $request): string
    {
        // the server (GWDG-hosting) fails to inform Symfony about the current scheme
        $target = $request->getSchemeAndHttpHost().$this->router->generate('login');
        if ($this->params->get('app.env') == 'prod') {
            $target = str_replace('http://', 'https://', $target);
        }
        return $target;
    }
}
