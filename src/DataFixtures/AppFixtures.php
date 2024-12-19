<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $userPasswordHasher) {}
    
    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);

        // Usual user creation
        $user = new User();
        $user->setEmail('user@test.com')
            ->setRoles(["ROLE_USER"])
            ->setPassword($this->userPasswordHasher->hashPassword($user, '00000000'));
        
        $manager->persist($user);

        // Admin user creation
        $adminUser = new User();
        $adminUser->setEmail('admin@test.com')
            ->setRoles(["ROLE_ADMIN"])
            ->setPassword($this->userPasswordHasher->hashPassword($adminUser, '00000000'));
        
        $manager->persist($adminUser);

        // Authors creation
        $listAuthors = [];
        for ($i=0; $i < 20; $i++) {
            $author = new Author();
            $author->setFirstName('FirstName ' . $i)
                ->setLastName('LastName ' . $i);
            
            $manager->persist($author);
            $listAuthors[] = $author;
        }

        // Books creation
        for ($i=0; $i < 20; $i++) {
            $book = new Book();
            $book->setTitle('titre ' . $i)
                ->setCoverText('Quatrième de couverture ' . $i)
                ->setAuthor($listAuthors[array_rand($listAuthors)])
                ->setComment("Commentaire du bibliothécaire n° " . $i);
            
            $manager->persist($book);
        }

        $manager->flush();
    }
}
