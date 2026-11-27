<?php

namespace App\Tests;

use App\Entity\Student;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EvalSmokeTest extends WebTestCase
{
    /**
     * Vérifie le flux "mot de passe oublié" : le formulaire doit aboutir à la
     * page check-email et un mail doit partir (MAILER_DSN fonctionnel).
     */
    public function testForgotPasswordSendsEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/reset-password');
        $this->assertResponseIsSuccessful();

        // on prend un email qui existe vraiment en base de test
        $em = static::getContainer()->get('doctrine')->getManager();
        $existingUser = $em->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.id')->setMaxResults(1)->getSingleResult();

        // le bundle reset-password limite à 1 demande / heure par utilisateur :
        // on purge les demandes existantes pour que le test soit rejouable
        $em->createQuery('DELETE FROM App\Entity\ResetPasswordRequest r')->execute();

        $form = $crawler->selectButton('Send password reset email')->form();
        $form['reset_password_request_form[email]'] = $existingUser->getEmail();

        $client->submit($form);
        $this->assertResponseRedirects('/reset-password/check-email');

        // Le mail est collecté par le profiler de test
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', $existingUser->getEmail());
    }

    /**
     * Vérifie la page "Mes notes" : la moyenne générale doit s'afficher.
     */
    public function testMyGradesShowsAverage(): void
    {
        $client = static::createClient();
        $student = $this->getStudent();

        $client->loginUser($student);
        $crawler = $client->request('GET', '/etudiant/' . $student->getId() . '/notes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Notes de');

        $average = $student->getAverage();
        $this->assertNotNull($average, 'getAverage() ne doit pas renvoyer null quand il y a des notes');
        $this->assertStringContainsString('/20', $crawler->filter('body')->text());
        $this->assertStringContainsString((string) $average, $crawler->filter('body')->text());
    }

    /**
     * Vérifie la page "Mes profs".
     */
    public function testMyProfs(): void
    {
        $client = static::createClient();
        $student = $this->getStudent();

        $client->loginUser($student);
        $crawler = $client->request('GET', '/etudiant/' . $student->getId() . '/profs');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes profs');
        $this->assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    /**
     * Bonus : envoi d'un mail à un professeur depuis la page Mes profs.
     */
    public function testMailToProfessor(): void
    {
        $client = static::createClient();
        $student = $this->getStudent();

        $client->loginUser($student);

        $crawler = $client->request('GET', '/etudiant/' . $student->getId() . '/profs/mail');
        $this->assertResponseIsSuccessful();

        $button = $crawler->selectButton('Envoyer');
        $this->assertNotNull($button, 'Le bouton Envoyer doit exister');
        $form = $button->form();
        $professor = $student->getClassLevel()->getProfessors()->first();
        $form['contact_professor[professor]'] = $professor->getId();
        $form['contact_professor[message]'] = 'Bonjour, je souhaite un rendez-vous.';
        if (isset($form['contact_professor[subject]'])) {
            $form['contact_professor[subject]'] = 'Demande de rendez-vous';
        }

        $client->submit($form);
        $this->assertResponseRedirects('/etudiant/' . $student->getId() . '/profs');
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', $student->getClassLevel()->getProfessors()->first()->getEmail());
        $this->assertEmailAddressContains($email, 'From', $student->getEmail());
    }

    private function getStudent(): Student
    {
        // on choisit un étudiant qui a au moins une note (les fixtures
        // attribuent classes/profs/évaluations de façon aléatoire)
        $em = static::getContainer()->get('doctrine')->getManager();

        return $em->createQuery('
                SELECT s FROM App\Entity\Student s
                JOIN s.grades g
                JOIN s.classLevel c
                JOIN c.professors p
                GROUP BY s.id
                HAVING COUNT(g) > 0
            ')
            ->setMaxResults(1)
            ->getSingleResult();
    }
}
