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
use Laika\Model\Contract\SchemaAbstract;

class SessionSchema extends SchemaAbstract
{
    /** @var string Database Table Name */
    protected string $table = 'sessions';

    // The connection is not declared here on purpose. SchemaAbstract's
    // constructor assigns it (falling back to 'default'), so redeclaring it
    // with a value here only suggests the connection is fixed when it is not.

    public function up(): void
    {
        Schema::on($this->connection)->createIfNotExists($this->table, function (Blueprint $table) {
            $table->string('id', 128);
            $table->blob('data');
            $table->timestamp('last_activity');

            // Indexes
            $table->primary('id');

            // gc() sweeps on last_activity every time it runs.
            $table->index('last_activity');
        });
    }
}
