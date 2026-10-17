<?php

declare(strict_types=1);

final class UserController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAdmin();

        $this->view('users/index', [
            'title' => 'Usuários',
            'user' => $user,
            'currentPath' => '/usuarios',
            'users' => User::all(),
        ]);
    }

    public function edit(): void
    {
        $user = $this->requireAdmin();

        $idText = request('id');

        if ($idText === '' || !ctype_digit($idText) || (int) $idText <= 0) {
            flash('error', 'ID de usuário inválido.');
            redirect('/usuarios');
        }

        $id = (int) $idText;
        $targetUser = User::findById($id);

        if (!$targetUser) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        $this->view('users/edit', [
            'title' => 'Editar perfil',
            'user' => $user,
            'currentPath' => '/usuarios',
            'targetUser' => $targetUser,
            'errors' => [],
        ]);
    }

    
    public function update(): void
    {
        $user = $this->requireAdmin();

        verifyCsrf();

        $idText = request('id');
        $name = request('name');
        $email = request('email');

        if ($idText === '' || !ctype_digit($idText) || (int) $idText <= 0) {
            flash('error', 'ID de usuário inválido.');
            redirect('/usuarios');
        }

        $id = (int) $idText;
        $targetUser = User::findById($id);

        if ($targetUser === null) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        $errors = User::updateProfile($id, $name, $email);

        if ($errors !== []) {
            $targetUser['name'] = $name;
            $targetUser['email'] = $email;

            $this->view('users/edit', [
                'title' => 'Editar perfil',
                'user' => $user,
                'currentPath' => '/usuarios',
                'targetUser' => $targetUser,
                'errors' => $errors,
            ]);

            return;
        }

        flash('success', 'Perfil atualizado com sucesso.');
        redirect('/usuarios');
    }


    public function delete(): void
    {
        $user = $this->requireAdmin();

        verifyCsrf();

        $idText = request('id');

        if ($idText === '' || !ctype_digit($idText) || (int) $idText <= 0) {
            flash('error', 'ID de usuário inválido.');
            redirect('/usuarios');
        }

        $id = (int) $idText;
        $currentUserId = (int) $user['id'];

        $result = User::deleteById($id, $currentUserId);

        switch ($result) {
            case 'success':
                flash('success', 'Usuário excluído com sucesso.');
                break;

            case 'not_found':
                flash('error', 'Usuário não encontrado.');
                break;

            case 'self':
                flash('error', 'Você não pode excluir sua própria conta.');
                break;

            case 'last_admin':
                flash('error', 'Não é possível excluir o último administrador.');
                break;

            case 'related_records':
                flash(
                    'error',
                    'Não foi possível excluir: existem registros relacionados a este usuário.'
                );
                break;

            default:
                flash('error', 'Ocorreu um erro ao excluir o usuário.');
                break;
        }

        redirect('/usuarios');
    }

    
    public function changeStatus(): void
    {
        $user = $this->requireAdmin();

        verifyCsrf();

        $idText = request('id');
        $action = request('action');

        if ($idText === '' || !ctype_digit($idText) || (int) $idText <= 0) {
            flash('error', 'ID de usuário inválido.');
            redirect('/usuarios');
        }

        if (!in_array($action, ['activate', 'deactivate'], true)) {
            flash('error', 'Ação inválida.');
            redirect('/usuarios');
        }

        $id = (int) $idText;
        $activate = $action === 'activate';

        $result = User::changeStatus(
            $id,
            (int) $user['id'],
            $activate
        );

        switch ($result) {
            case 'success':
                flash(
                    'success',
                    $activate
                        ? 'Perfil reativado com sucesso.'
                        : 'Perfil inativado com sucesso.'
                );
                break;

            case 'not_found':
                flash('error', 'Usuário não encontrado.');
                break;

            case 'self':
                flash('error', 'Você não pode alterar o status do seu próprio perfil.');
                break;

            case 'last_admin':
                flash('error', 'Não é possível inativar o último administrador ativo.');
                break;

            case 'unchanged':
                flash('error', 'O perfil já está com esse status.');
                break;

            default:
                flash('error', 'Ocorreu um erro ao alterar o status do perfil.');
                break;
        }

        redirect('/usuarios/editar?id=' . $id);
    }

}

