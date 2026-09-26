<?php

declare(strict_types=1);

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->guestOnly();
        $this->view('auth/login', [
            'title' => 'Entrar',
            'user' => null,
            'currentPath' => '/login',
            'email' => '',
        ]);
    }

    public function login(): void
    {
        $this->guestOnly();
        verifyCsrf();

        $email = request('email');
        $password = $_POST['password'] ?? '';

        if (!is_string($password) || !AuthService::attempt($email, $password)) {
            flash('error', 'E-mail ou senha inválidos.');
            $this->view('auth/login', [
                'title' => 'Entrar',
                'user' => null,
                'currentPath' => '/login',
                'email' => $email,
            ]);
            return;
        }

        redirect('/');
    }

    public function showRegister(): void
    {
        $this->guestOnly();
        $this->view('auth/register', [
            'title' => 'Cadastro',
            'user' => null,
            'currentPath' => '/cadastro',
            'name' => '',
            'email' => '',
            'errors' => [],
        ]);
    }

    public function register(): void
    {
        $this->guestOnly();
        verifyCsrf();

        $name = request('name');
        $email = request('email');
        $password = $_POST['password'] ?? '';
        $password = is_string($password) ? $password : '';

        $errors = User::register($name, $email, $password);
        if ($errors !== []) {
            $this->view('auth/register', [
                'title' => 'Cadastro',
                'user' => null,
                'currentPath' => '/cadastro',
                'name' => $name,
                'email' => $email,
                'errors' => $errors,
            ]);
            return;
        }

        flash('success', 'Cadastro concluído. Bem-vindo ao clube.');
        redirect('/');
    }

    public function logout(): void
    {
        $this->requireAuth();
        verifyCsrf();
        AuthService::logout();
        redirect('/login');
    }
}
