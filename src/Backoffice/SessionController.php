<?php

namespace Bakery\Backoffice;

use Waypoint\Attributes\{Body, Controller, Get, Inject, Post};
use Waypoint\Flash\Flash;
use Waypoint\Http\{Redirect, Response, View};
use Waypoint\I18n\Translator;
use Waypoint\JWT;

/** Signing in to the back office: a classic form post, a JWT in an HttpOnly cookie, a flash message. */
#[Controller('/backoffice')]
final class SessionController
{
    #[Inject]
    private Users $users;

    #[Inject]
    private Flash $flash;

    #[Inject]
    private Translator $translator;

    #[Get('/login')]
    public function form(): View
    {
        return new View('Backoffice/Login', new LoginForm(), layout: '_Backoffice.php');
    }

    /** A View with the form while it is invalid answers 422. */
    #[Post('/login')]
    public function login(
        #[Body(
            validate: false
        )]
        LoginForm $form,
        Response $res,
    ): View|Redirect {
        $user = $form->isValid ? $this->users->verify($form->email, $form->password) : null;
        if ($user === null) {
            if ($form->isValid) {
                $form->fail('password', $this->translator->t('Wrong email or password.'));
            }
            return new View('Backoffice/Login', $form, layout: '_Backoffice.php');
        }
        JWT::signIn($res, $user);
        $this->flash->success($this->translator->t('Welcome, {name}.', ['name' => $user['name']]));
        return new Redirect('/backoffice', 303);
    }

    #[Post('/logout')]
    public function logout(Response $res): Redirect
    {
        JWT::signOut($res);
        $this->flash->info($this->translator->t('You are signed out.'));
        return new Redirect('/backoffice/login', 303);
    }
}
