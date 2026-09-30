<?php
// Pure table schema definitions - edit here to add/modify columns.
// install/updater.php adds missing tables/columns on every version bump.

return [
    'settings' => [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'key' => 'TEXT NOT NULL UNIQUE',
        'value' => 'TEXT',
    ],

    // One row per translated target record (page or product) - shown on the
    // overview tab, so the admin can see what the hooks actually did.
    'log' => [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'ts' => 'INTEGER NOT NULL',
        'type' => 'VARCHAR(20) NOT NULL',         // page | product | test
        'action' => 'VARCHAR(30) NULL',           // all | teaser | descriptions | metas
        'source_id' => 'INTEGER NULL',
        'target_id' => 'INTEGER NULL',
        'source_lang' => 'VARCHAR(10) NULL',
        'target_lang' => 'VARCHAR(10) NULL',
        'characters' => 'INTEGER DEFAULT 0',      // billed characters reported by DeepL
        'status' => 'VARCHAR(10) NOT NULL',       // ok | error | skipped
        'message' => 'TEXT NULL',
        'user' => 'VARCHAR(100) NULL',
    ],
];
