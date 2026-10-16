<?php

namespace Bakery\Backoffice;

use Waypoint\Attributes\{Controller, Get, Ignore, Inject, Post};
use Waypoint\Environment;
use Waypoint\Flash\Flash;
use Waypoint\Http\{Redirect, Request, Response, View};
use Waypoint\I18n\Translator;
use Waypoint\JWT;

/** Signing in to the back office: a classic form post, a JWT in an HttpOnly cookie, a flash message. */
#[Controller('/backoffice')]
#[Ignore]
final class SessionController
{
    #[Inject]
    private Users $users;

    #[Inject]
    private Flash $flash;

    #[Inject]
    private Translator $translator;

    #[Inject]
    private Environment $env;

    #[Get('/login')]
    public function form(): View
    {
        return new View('Backoffice/Login', ['form' => new LoginForm(), 'errors' => []], layout: '_Backoffice.php');
    }

    #[Post('/login')]
    public function login(Request $req, Response $res): View|Redirect
    {
        $form = new LoginForm($req->body());
        $user = $form->isValid ? $this->users->verify($form->email, $form->password) : null;
        if ($form->isValid && $user === null) {
            $form->fail('password', $this->translator->t('Wrong email or password.'));
        }
        if ($user === null) {
            $res->status(422);
            return new View('Backoffice/Login', ['form' => $form, 'errors' => $form->errors], layout: '_Backoffice.php');
        }
        $res->withCookie('session', JWT::encode($user->claims()), secure: !$this->env->isDev());
        $this->flash->success($this->translator->t('Welcome, {name}.', ['name' => $user->name]));
        return new Redirect('/backoffice', 303);
    }

    #[Post('/logout')]
    public function logout(Response $res): Redirect
    {
        $res->withoutCookie('session');
        $this->flash->info($this->translator->t('You are signed out.'));
        return new Redirect('/backoffice/login', 303);
    }
}
