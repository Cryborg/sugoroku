<?php

namespace Trapped\Controllers;

use Trapped\Models\User;

/**
 * AuthController - Gestion de l'authentification
 */
class AuthController
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(string $email, string $password, string $username): array
    {
        // Validation
        if (empty($email) || empty($password) || empty($username)) {
            return $this->error('Tous les champs sont requis');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Email invalide');
        }

        if (strlen($password) < 6) {
            return $this->error('Le mot de passe doit contenir au moins 6 caractères');
        }

        if (strlen($username) < 3) {
            return $this->error('Le nom d\'utilisateur doit contenir au moins 3 caractères');
        }

        // Créer l'utilisateur
        $user = new User();
        $user->email = $email;
        $user->passwordHash = User::hashPassword($password);
        $user->username = $username;

        if (!$user->create()) {
            return $this->error('Cet email est déjà utilisé');
        }

        // Démarrer la session
        $this->startSession($user);

        return $this->success([
            'user' => $user->toArray(),
            'message' => 'Inscription réussie'
        ]);
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(string $email, string $password): array
    {
        // Validation
        if (empty($email) || empty($password)) {
            return $this->error('Email et mot de passe requis');
        }

        // Charger l'utilisateur
        $user = new User();
        if (!$user->loadByEmail($email)) {
            return $this->error('Email ou mot de passe incorrect');
        }

        // Vérifier le mot de passe
        if (!$user->verifyPassword($password)) {
            return $this->error('Email ou mot de passe incorrect');
        }

        // Mettre à jour la dernière connexion
        $user->updateLastLogin();

        // Démarrer la session
        $this->startSession($user);

        return $this->success([
            'user' => $user->toArray(),
            'message' => 'Connexion réussie'
        ]);
    }

    /**
     * Déconnexion
     */
    public function logout(): array
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return $this->success(['message' => 'Déconnexion réussie']);
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public function getCurrentUser(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return $this->error('Non authentifié', 401);
        }

        $user = new User();
        if (!$user->load($_SESSION['user_id'])) {
            return $this->error('Utilisateur introuvable', 404);
        }

        return $this->success(['user' => $user->toArray()]);
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public static function isAuthenticated(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return isset($_SESSION['user_id']);
    }

    /**
     * Récupère l'ID de l'utilisateur connecté
     */
    public static function getUserId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Démarre une session pour l'utilisateur
     */
    private function startSession(User $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_username'] = $user->username;
    }

    /**
     * Réponse de succès
     */
    private function success(mixed $data): array
    {
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Réponse d'erreur
     */
    private function error(string $message, int $code = 400): array
    {
        http_response_code($code);
        return [
            'success' => false,
            'error' => $message
        ];
    }
}
