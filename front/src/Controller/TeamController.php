<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\PokedexApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/teams')]
final class TeamController extends AbstractController
{
    public function __construct(private readonly PokedexApi $api)
    {
    }

    #[Route('', name: 'team_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('team/index.html.twig', ['teams' => $this->api->fetchTeams()]);
    }

    #[Route('/new', name: 'team_new', methods: ['GET'])]
    public function new(): Response
    {
        // Le formulaire, sa validation et l'appel POST à l'API sont dans le Live Component <twig:TeamBuilder />.
        return $this->render('team/new.html.twig');
    }

    #[Route('/{id}/delete', name: 'team_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-team-'.$id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->api->deleteTeam($id);

        // Turbo envoie « Accept: text/vnd.turbo-stream.html » : on répond par un flux qui retire la carte du DOM.
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('team/delete.stream.html.twig', ['id' => $id]);
        }

        $this->addFlash('success', 'Équipe supprimée.');

        return $this->redirectToRoute('team_index', status: Response::HTTP_SEE_OTHER);
    }
}
