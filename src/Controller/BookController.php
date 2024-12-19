<?php

namespace App\Controller;

use App\Entity\Book;
use OpenApi\Attributes as OA;
use App\Repository\BookRepository;
use App\Service\VersioningService;
use App\Repository\AuthorRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BookController extends AbstractController
{
    #[OA\Response(response: 200, description: "Retrieve all books", content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))))]
    #[OA\Parameter(name: 'page', in: 'query', description: 'The page we want to retrieve', schema: new OA\Schema(type: 'int'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'The number of items we want to retrieve', schema: new OA\Schema(type: 'int'))]
    #[OA\Tag(name: 'Books')]
    /**
     * Retrieve all books
     * 
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/books', name: 'book', methods: ['GET'])]
    public function getAllBooks(BookRepository $bookRepo, Request $request, SerializerInterface $serializer, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $jsonBooksList = $cachePool->get($idCache, function (ItemInterface $item) use ($bookRepo, $page, $limit, $serializer) {
            echo "Initialisation cache";
            $item->tag("booksCache");
            $booksList = $bookRepo->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getBooks']);
            return $serializer->serialize($booksList, 'json', $context);
        });
        
        return new JsonResponse($jsonBooksList, Response::HTTP_OK, [], true);
    }

    #[OA\Response(response: 200, description: "Retrieve a book", content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))))]
    #[OA\Tag(name: 'Books')]
    /**
     * Retrieve a book
     * 
     * @param Book $book
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'detailBook', methods: ['GET'])]
    public function getDetailBook(Book $book, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $version = $versioningService->getVersion();
        $context->setVersion($version);
        $book = $serializer->serialize($book, 'json', $context);
        return new JsonResponse($book, Response::HTTP_OK, [], true);
    }

    #[OA\Response(response: 204, description: "Delete a book", content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Book::class))))]
    #[OA\Tag(name: 'Books')]
    /**
     * Delete a book
     * 
     * @param Book $book
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $em->remove($book);
        $em->flush();
        
        $cachePool->invalidateTags(['booksCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Create a book
     * 
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param Request $request
     * @param UrlGeneratorInterface $urlGenerator
     * @param AuthorRepository $authorRepo
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createBook(SerializerInterface $serializer, EntityManagerInterface $em, Request $request, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepo, ValidatorInterface $validator, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $content = $request->toArray();
        $authorId = $content['authorId'] ?? -1;

        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $book->setAuthor($authorRepo->find($authorId));
        
        $em->persist($book);
        $em->flush();

        $cachePool->invalidateTags(['booksCache']);

        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['location' => $location], true);
    }

    /**
     * @param Book $currentBook
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param Request $request
     * @param AuthorRepository $authorRepo
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'updateBook', methods: ['PUT'])]
    public function updateBook(Book $currentBook, SerializerInterface $serializer, EntityManagerInterface $em, Request $request, AuthorRepository $authorRepo, ValidatorInterface $validator, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        $content = $request->toArray();
        $authorId = $content['authorId'] ?? -1;
        $currentBook->setAuthor($authorRepo->find($authorId));

        $errors = $validator->validate($currentBook);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        
        $em->persist($currentBook);
        $em->flush();

        $cachePool->invalidateTags(['booksCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}