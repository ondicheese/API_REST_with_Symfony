<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AuthorController extends AbstractController
{
    /**
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param AuthorRepository $authorRepo
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/authors', name: 'authors', methods: ['GET'])]
    public function getAllAuthors(SerializerInterface $serializer, Request $request, AuthorRepository $authorRepo, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $cacheId = 'getAllAuthors-' . $page . '-' . $limit; 

        $jsonAuthors =  $cachePool->get($cacheId, function($item) use ($page, $limit, $authorRepo, $serializer) {
            echo "Initialisation du cache";
            $item->tag('authorsCache');
            $authorsList = $authorRepo->getAllAuthors($page, $limit);
            $context = SerializationContext::create()->setGroups(['getAuthors']);
            return $serializer->serialize($authorsList, 'json', $context);
        });

        return new JsonResponse($jsonAuthors, Response::HTTP_OK, [], true);
    }

    /**
     * @param Author $author,
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/authors/{id}', name: 'getAuthor', methods: ['GET'])]
    public function getAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getAuthors']);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);

        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    /**
     * @param Author $author,
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    public function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {     
        $em->remove($author);
        $em->flush();

        $cachePool->invalidateTags(['authorsCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param BookRepository $bookRepo
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/authors', name: 'createAuthor', methods: ['POST'])]
    public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, BookRepository $bookRepo, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        $errors = $validator->validate($author);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        /*
        $content = $request->toArray();
        $bookId = $content['bookId'] ?? -1;
        $book = $bookRepo->find($bookId);
        */
        
        $em->persist($author);
        $em->flush();

        //$author->addBook($book);

        $cachePool->invalidateTags(['authorsCache']);

        $location = $urlGenerator->generate('getAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $context = SerializationContext::create()->setGroups(['getAuthors']);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);
        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ['location' => $location], true);
    }

    /**
     * @param Author $currentAuthor
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param BookRepository $bookRepo
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/authors/{id}', name: 'updateAuthor', methods: ['PUT'])]
    public function updateAuthor(Author $currentAuthor, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, BookRepository $bookRepo, ValidatorInterface $validator, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $newAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $currentAuthor->setFirstName($newAuthor->getFirstname());
        $currentAuthor->setLastName($newAuthor->getLastname());

        $content = $request->toArray();
        $bookId = $content['bookId'] ?? -1;
        $book = $bookRepo->find($bookId);
        $book->setAuthor($currentAuthor);

        $errors = $validator->validate($currentAuthor);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($currentAuthor);
        $em->flush();

        $cachePool->invalidateTags(['authorsCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}