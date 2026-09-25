<?php

namespace App\Entity;

use App\Enum\ReceiptStatus;
use App\Repository\ReceiptRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReceiptRepository::class)]
final class Receipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 500)]
    private string $storagePath;

    #[ORM\Column(enumType: ReceiptStatus::class)]
    private ReceiptStatus $status = ReceiptStatus::Pending;

    #[ORM\Column]
    private ?DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    /**
     * @var Collection<int, ReceiptItem>
     */
    #[ORM\OneToMany(targetEntity: ReceiptItem::class, mappedBy: 'receipt', orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ocrText = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $purchasedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $merchant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $inn = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalAmount = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getStatus(): ReceiptStatus
    {
        return $this->status;
    }

    public function setStatus(ReceiptStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUploadedAt(): ?DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    /**
     * @return Collection<int, ReceiptItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ReceiptItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setReceipt($this);
        }

        return $this;
    }

    public function removeItem(ReceiptItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getReceipt() === $this) {
                $item->setReceipt(null);
            }
        }

        return $this;
    }

    public function getOcrText(): ?string
    {
        return $this->ocrText;
    }

    public function setOcrText(string $ocrText): static
    {
        $this->ocrText = $ocrText;

        return $this;
    }

    public function getPurchasedAt(): ?DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(DateTimeImmutable $purchasedAt): static
    {
        $this->purchasedAt = $purchasedAt;

        return $this;
    }

    public function getMerchant(): ?string
    {
        return $this->merchant;
    }

    public function setMerchant(?string $merchant): static
    {
        $this->merchant = $merchant;

        return $this;
    }

    public function getInn(): ?string
    {
        return $this->inn;
    }

    public function setInn(?string $inn): static
    {
        $this->inn = $inn;

        return $this;
    }

    public function getTotalAmount(): ?int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(?int $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }
}
