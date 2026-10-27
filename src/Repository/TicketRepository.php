<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * Tickets the given user may see, newest activity first.
     * Customers see their own; agents see assigned-to-them and unassigned; admins see all.
     *
     * @return list<Ticket>
     */
    public function findVisibleTo(User $user, ?TicketStatus $status = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('r', 'a')
            ->join('t.requester', 'r')
            ->leftJoin('t.assignee', 'a')
            ->orderBy('t.updatedAt', 'DESC');

        $this->restrictTo($qb, $user);

        if (null !== $status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }
        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('LOWER(t.title) LIKE :q OR LOWER(t.description) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($search)).'%');
        }

        /** @var list<Ticket> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Active tickets whose first-response deadline has passed and that nobody has been told about yet.
     *
     * @return list<Ticket>
     */
    public function findNewSlaBreaches(DateTimeImmutable $now): array
    {
        /** @var list<Ticket> $result */
        $result = $this->createQueryBuilder('t')
            ->andWhere('t.status IN (:active)')
            ->andWhere('t.responseDueAt IS NOT NULL AND t.responseDueAt < :now')
            ->andWhere('t.slaBreachNotified = false')
            ->setParameter('active', [TicketStatus::Open, TicketStatus::InProgress])
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return array<string, int> status value => count */
    public function countByStatusFor(User $user): array
    {
        $qb = $this->createQueryBuilder('t')->select('t.status AS status, COUNT(t.id) AS total')->groupBy('t.status');
        $this->restrictTo($qb, $user);

        $counts = [];
        /** @var list<array{status: TicketStatus, total: int|string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();
        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['total'];
        }

        return $counts;
    }

    private function restrictTo(QueryBuilder $qb, User $user): void
    {
        if (\in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return;
        }
        if ($user->isAgent()) {
            $qb->andWhere('t.assignee = :me OR t.assignee IS NULL')->setParameter('me', $user);

            return;
        }
        $qb->andWhere('t.requester = :me')->setParameter('me', $user);
    }
}
