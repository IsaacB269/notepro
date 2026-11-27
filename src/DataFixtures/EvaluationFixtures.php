<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\ClassLevel;
use App\Entity\Evaluation;
use App\Entity\Professor;
use App\Entity\Subject;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class EvaluationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $baremeValues = [10, 20, 30, 50, 100];

        $profs = $manager->getRepository(Professor::class)->findAll();
        $counter = 0;
        foreach ($profs as $professor) {
            foreach ($professor->getSubjects() as $subject) {
                foreach ($professor->getClassLevels() as $classLevel) {
                    for ($i = 0; $i < 2; $i++) {
                        $eval = new Evaluation();
                        $eval->setLabel($faker->sentence(3, true));
                        $eval->setBareme($faker->randomElement($baremeValues));
                        $eval->setDate($faker->dateTimeBetween('-1 month', 'now'));
                        $eval->setProfessor($professor);
                        $eval->setSubject($subject);
                        $eval->setClassLevel($classLevel);
                        $eval->setCategory($this->getReference('category_' . $faker->numberBetween(0, 3), Category::class));

                        $manager->persist($eval);
                        $this->addReference('evaluation_' . $counter++, $eval);
                    }
                }
            }
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
            ClassLevelFixtures::class,
            SubjectFixtures::class,
            UserFixtures::class,
        ];
    }
}
