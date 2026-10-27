<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['response_due_at'])]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 200)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 10000)]
    private string $description = '';

    #[ORM\Column(length: 20, enumType: TicketStatus::class)]
    private TicketStatus $status = TicketStatus::Open;

    #[ORM\Column(length: 20, enumType: TicketPriority::class)]
    private TicketPriority $priority = TicketPriority::Normal;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $requester;

    #[ORM\ManyToOne]
    private ?User $assignee = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /** Deadline for the first agent response; null once an agent has responded. */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $responseDueAt = null;

    #[ORM\Column]
    private bool $slaBreachNotified = false;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'ticket', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;

    public function __construct(User $requester, TicketPriority $priority = TicketPriority::Normal)
    {
        $this->requester = $requester;
        $this->priority = $priority;
        $this->comments = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->responseDueAt = $this->createdAt->modify(\sprintf('+%d hours', $priority->slaHours()));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = trim($description);

        return $this;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function setStatus(TicketStatus $status): static
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getPriority(): TicketPriority
    {
        return $this->priority;
    }

    public function setPriority(TicketPriority $priority): static
    {
        $this->priority = $priority;
        if (null !== $this->responseDueAt) {
            $this->responseDueAt = $this->createdAt->modify(\sprintf('+%d hours', $priority->slaHours()));
        }

        return $this;
    }

    public function getRequester(): User
    {
        return $this->requester;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function assignTo(?User $assignee): static
    {
        $this->assignee = $assignee;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getResponseDueAt(): ?DateTimeImmutable
    {
        return $this->responseDueAt;
    }

    public function isSlaBreachNotified(): bool
    {
        return $this->slaBreachNotified;
    }

    public function markSlaBreachNotified(): static
    {
        $this->slaBreachNotified = true;

        return $this;
    }

    public function isOverdue(?DateTimeImmutable $now = null): bool
    {
        return null !== $this->responseDueAt
            && $this->status->isActive()
            && $this->responseDueAt < ($now ?? new DateTimeImmutable());
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
        if ($comment->getAuthor()->isAgent() && !$comment->isInternal()) {
            $this->responseDueAt = null; // first agent reply stops the SLA clock
        }
        $this->touch();

        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
