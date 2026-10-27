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
            'username' => '',
            'email' => '',
            'errors' => [],
        ]);
    }

    public function register(): void
    {
        $this->guestOnly();
        verifyCsrf();

        $username = request('username');
        $email = request('email');
        $password = $_POST['password'] ?? '';
        $password = is_string($password) ? $password : '';

        $errors = User::register($username, $email, $password);
        if ($errors !== []) {
            $this->view('auth/register', [
                'title' => 'Cadastro',
                'user' => null,
                'currentPath' => '/cadastro',
                'username' => $username,
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

    public function checkUsernameAvailability()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $username = request('username');

            $username_exists = User::usernameExists($username);

            $resposta = [
                'available' => !$username_exists
            ];
            http_response_code(200); // 200 - OK
        } catch (Exception $e) {
            $resposta = [
                'status' => 'error',
                'mensagem' => $e->getMessage()
            ];
            http_response_code(400); // 400 - Bad Request
        }
        echo json_encode($resposta);
        exit; 
    }
}