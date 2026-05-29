<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    #[Route('/', name: 'app_root', methods: ['GET'])]
    #[Route('/app', name: 'app_main', methods: ['GET'])]
    #[Route('/account', name: 'app_account_root', methods: ['GET'])]
    #[Route('/account/{path}', name: 'app_account', requirements: ['path' => '.+'], methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('app/index.html.twig');
    }
}
