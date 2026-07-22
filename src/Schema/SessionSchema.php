<?php
/**
 * Laika Session
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP MVC Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Laika\Session\Schema;

use Laika\Model\Schema\Schema;
use Laika\Model\Schema\Blueprint;
use Laika\Core\Abstracts\SchemaAbstract;

class SessionSchema extends SchemaAbstract
{
    /** @var string Database Table Name */
    protected string $table = 'sessions';

    /** @var string Database Connection Name */
    protected string $connection = 'default';

    public function up(): void
    {
        Schema::on($this->connection)->createIfNotExists($this->table, function (Blueprint $table) {
            $table->string('id', 128);
            $table->blob('data');
            $table->timestamp('last_activity');

            // Indexes
            $table->primary('id');
        });
    }
}
