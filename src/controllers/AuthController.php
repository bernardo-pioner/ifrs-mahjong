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
        $password = is_string($password) ? $password : '';
        $resultado = AuthService::attempt($email, $password);

        if ($resultado === 'unverified') {
            flash('error', 'Confirme seu e-mail para entrar. O link foi enviado no cadastro.');
            $this->view('auth/login', [
                'title' => 'Entrar',
                'user' => null,
                'currentPath' => '/login',
                'email' => $email,
            ]);
            return;
        }

        if ($resultado !== 'ok') {
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
            'notifyEmails' => true,
            'registerResponse' => null,
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

        $notifyEmails = isset($_POST['emails_notificacao']);
        $registerResponse = User::register($username, $email, $password, $notifyEmails);
        if ($registerResponse === 'mail') {
            flash('error', 'Não foi possível enviar o e-mail de confirmação. Tente novamente.');
            $this->view('auth/register', [
                'title' => 'Cadastro',
                'user' => null,
                'currentPath' => '/cadastro',
                'username' => $username,
                'email' => $email,
                'notifyEmails' => $notifyEmails,
                'registerResponse' => null,
            ]);
            return;
        }

        if ($registerResponse !== true) {
            $this->view('auth/register', [
                'title' => 'Cadastro',
                'user' => null,
                'currentPath' => '/cadastro',
                'username' => $username,
                'email' => $email,
                'notifyEmails' => $notifyEmails,
                'registerResponse' => false,
            ]);
            return;
        }

        flash('success', 'Cadastro feito. Enviamos um link de confirmação para o seu e-mail. Confirme para entrar.');
        redirect('/login');
    }

    public function confirmEmail(): void
    {
        $resultado = EmailCode::consumeVerificationToken(request('token'));
        if ($resultado === 'ok') {
            flash('success', 'E-mail confirmado. Agora você pode entrar.');
        } elseif ($resultado === 'already') {
            flash('success', 'Este e-mail já estava confirmado. Você pode entrar.');
        } else {
            flash('error', 'O link de confirmação é inválido ou expirou.');
        }

        redirect('/login');
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

    public function checkEmailAvailability()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $email = request('email');

            $email_exists = User::emailExists($email);

            $resposta = [
                'available' => !$email_exists
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