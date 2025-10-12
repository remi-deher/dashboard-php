<?php
// Fichier: /src/Controller/AdminController.php

// On a besoin du modèle pour travailler
require_once __DIR__ . '/../Model/ServiceModel.php';

class AdminController
{
    private ServiceModel $serviceModel;

    public function __construct(ServiceModel $serviceModel)
    {
        $this->serviceModel = $serviceModel;
    }

    // Affiche la page principale de l'admin (le tableau et le formulaire)
    public function index(): void
    {
        $edit_service = null;
        if (isset($_GET['edit'])) {
            $edit_service = $this->serviceModel->getById((int)$_GET['edit']);
        }

        $all_services = $this->serviceModel->getAll();

        // Charge la vue en lui passant les données nécessaires
        require __DIR__ . '/../../templates/admin.php';
    }

    // Gère la soumission du formulaire (ajout ou modification)
    public function save(): void
    {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $this->serviceModel->create($_POST);
        }

        if ($action === 'update') {
            $this->serviceModel->update((int)$_POST['id'], $_POST);
        }

        // Redirection après traitement
        header('Location: /admin.php');
        exit;
    }

    // Gère la suppression d'un service
    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->serviceModel->delete($id);
        }

        header('Location: /admin.php');
        exit;
    }
}
