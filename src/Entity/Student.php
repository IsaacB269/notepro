<?php

namespace App\Entity;

use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student extends User
{
    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Grade::class, orphanRemoval: true)]
    private Collection $grades;

    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ClassLevel $classLevel = null;

    public function __construct()
    {
        parent::__construct();
        $this->grades = new ArrayCollection();
    }

    /**
     * @return Collection<int, Grade>
     */
    public function getGrades(): Collection
    {
        return $this->grades;
    }

    public function addGrade(Grade $grade): static
    {
        if (!$this->grades->contains($grade)) {
            $this->grades->add($grade);
            $grade->setStudent($this);
        }

        return $this;
    }

    public function removeGrade(Grade $grade): static
    {
        if ($this->grades->removeElement($grade)) {
            // set the owning side to null (unless already changed)
            if ($grade->getStudent() === $this) {
                $grade->setStudent(null);
            }
        }

        return $this;
    }

    public function getClassLevel(): ?ClassLevel
    {
        return $this->classLevel;
    }

    public function setClassLevel(?ClassLevel $classLevel): static
    {
        $this->classLevel = $classLevel;

        return $this;
    }

    public function getGradeByEval (Evaluation $evaluation): ?Grade
    {
        foreach ($this->getGrades() as $grade){
            if ($grade->getEvaluation() === $evaluation){
                return $grade;
            }
        }
        return null;
    }

    /**
     * Moyenne générale de l'étudiant, ramenée sur 20.
     * Chaque note est normalisée sur 20 (note / barème * 20) pour que les
     * évaluations avec des barèmes différents soient comparables.
     * Les évaluations où l'étudiant était absent (note non renseignée) ne comptent pas.
     */
    public function getAverage(): ?float
    {
        $sum = 0;
        $count = 0;
        foreach ($this->getGrades() as $grade) {
            if ($grade->getGrade() === null) {
                continue;
            }
            $bareme = $grade->getEvaluation()?->getBareme();
            if ($bareme === null || $bareme === 0) {
                continue;
            }
            $sum += (float) $grade->getGrade() / $bareme * 20;
            $count++;
        }

        return $count > 0 ? round($sum / $count, 2) : null;
    }

    /**
     * Moyenne de l'étudiant pour une matière donnée, ramenée sur 20
     * (chaque note normalisée sur 20, les absences non comptées).
     */
    public function getAverageBySubject(\App\Entity\Subject $subject): ?float
    {
        $sum = 0;
        $count = 0;
        foreach ($this->getGrades() as $grade) {
            if ($grade->getGrade() === null) {
                continue;
            }
            $evaluation = $grade->getEvaluation();
            $bareme = $evaluation?->getBareme();
            if ($bareme === null || $bareme === 0 || $evaluation->getSubject() === null) {
                continue;
            }
            $gradeSubject = $evaluation->getSubject();
            // comparaison par id quand il existe, sinon par identité d'objet
            if ($gradeSubject->getId() !== null && $subject->getId() !== null
                ? $gradeSubject->getId() !== $subject->getId()
                : $gradeSubject !== $subject) {
                continue;
            }
            $sum += (float) $grade->getGrade() / $bareme * 20;
            $count++;
        }

        return $count > 0 ? round($sum / $count, 2) : null;
    }
}
