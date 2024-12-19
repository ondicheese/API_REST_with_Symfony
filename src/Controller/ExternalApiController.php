<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

 
class ExternalApiController extends AbstractController
{
    /**
     * This method calls https://api.github.com/repos/symfony/symfony-docs
     * retrieve the data and transmit it as is.
     *
     * Doc http client:
     * https://symfony.com/doc/current/http_client.html
     *
     * @param HttpClientInterface $httpClient
     * @return JsonResponse
     */
    #[Route('/api/external/getSfDoc', name: 'external_api', methods: 'GET')]
            public function getSymfonyDoc(HttpClientInterface $httpClient): JsonResponse
    {
        // URL call
        $response = $httpClient->request(
            'GET',
            'https://api.github.com/repos/symfony/symfony-docs'
        );
        return new JsonResponse($response->getContent(), $response->getStatusCode(), [], true);
    }
}
