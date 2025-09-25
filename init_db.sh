#!/bin/bash

# xsukax Shared Timers - Database Initialization Script
# Creates SQLite database with proper schema for local timezone support

DB_FILE="timers.db"

echo "=== xsukax Shared Timers Database Setup ==="
echo "Setting up database with local timezone support..."

# Create the database file if it doesn't exist
if [ ! -f "$DB_FILE" ]; then
    touch "$DB_FILE"
    echo "✓ Created database file: $DB_FILE"
else
    echo "✓ Database file exists: $DB_FILE"
fi

# Create the database schema
sqlite3 "$DB_FILE" << 'EOF'
-- Create main timers table
CREATE TABLE IF NOT EXISTS timers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    start_timestamp INTEGER NOT NULL,
    duration_seconds INTEGER NOT NULL,
    creator_ip VARCHAR(45) NOT NULL,
    created_at INTEGER NOT NULL,
    title VARCHAR(255) DEFAULT 'Timer'
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_creator_ip ON timers(creator_ip);
CREATE INDEX IF NOT EXISTS idx_start_timestamp ON timers(start_timestamp);
CREATE INDEX IF NOT EXISTS idx_created_at ON timers(created_at);

-- Verify table structure
.schema timers
EOF

echo "✓ Database schema created successfully"

# Set proper file permissions
chmod 664 "$DB_FILE" 2>/dev/null || echo "⚠ Could not set file permissions (may require sudo)"

echo ""
echo "=== Database Schema ==="
echo "Table: timers"
echo "- id: Unique timer identifier (auto-increment)"
echo "- start_timestamp: Unix timestamp when timer started"
echo "- duration_seconds: Total timer duration in seconds"
echo "- creator_ip: IP address of timer creator"
echo "- created_at: Unix timestamp when timer was created"
echo "- title: User-defined timer title"
echo ""
echo "=== Features ==="
echo "✓ All timestamps stored as Unix timestamps"
echo "✓ Client-side timezone conversion"
echo "✓ Performance indexes created"
echo "✓ Multi-user support via IP isolation"
echo ""
echo "=== Next Steps ==="
echo "1. Place timer.php in your web directory"
echo "2. Ensure web server can read/write $DB_FILE"
echo "3. Access via web browser"
echo ""
echo "Database ready! 🚀"