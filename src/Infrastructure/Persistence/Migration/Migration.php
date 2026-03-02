<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration;

interface Migration
{
    /**
     * Run the migration.
     */
    public function up(): void;
    
    /**
     * Get the description of the migration.
     */
    public function getDescription(): string;
}
