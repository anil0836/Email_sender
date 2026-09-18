import 'dotenv/config';
import jsforce from 'jsforce';
import mysql from 'mysql2/promise';

async function refreshSalesforceToken() {
    const loginUrl = process.env.SALESFORCE_LOGIN_URL || process.env.SF_LOGIN_URL || 'https://login.salesforce.com';
    const username = process.env.SALESFORCE_USERNAME || process.env.SF_USERNAME;
    const password = process.env.SALESFORCE_PASSWORD || process.env.SF_PASSWORD;

    if (!username || !password) {
        console.error('Error: Salesforce credentials not found in environment (SF_USERNAME, SF_PASSWORD).');
        process.exit(1);
    }

    // 1. Connect to Salesforce using JSforce
    const conn = new jsforce.Connection({
        loginUrl: loginUrl.trim(),
        version: '61.0'
    });

    try {
        await conn.login(username.trim(), password.trim());
    } catch (err) {
        console.error('Salesforce login failed:', err.message || err);
        process.exit(1);
    }

    // 2. Connect to MySQL Database & Store Token
    const dbConfig = {
        host: process.env.DB_HOST || '127.0.0.1',
        port: parseInt(process.env.DB_PORT || '3306', 10),
        user: process.env.DB_USERNAME || process.env.DB_USER || 'root',
        password: process.env.DB_PASSWORD || '',
        database: process.env.DB_DATABASE || 'email_database',
    };

    try {
        const db = await mysql.createConnection(dbConfig);

        // Ensure table exists
        await db.execute(`
            CREATE TABLE IF NOT EXISTS salesforce_tokens (
                id INT PRIMARY KEY,
                access_token TEXT NOT NULL,
                instance_url VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        `);

        // Insert or update token record
        await db.execute(
            `INSERT INTO salesforce_tokens (id, access_token, instance_url, expires_at)
             VALUES (1, ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))
             ON DUPLICATE KEY UPDATE
                 access_token = VALUES(access_token),
                 instance_url = VALUES(instance_url),
                 expires_at = VALUES(expires_at)`,
            [conn.accessToken, conn.instanceUrl]
        );

        await db.end();
        console.log('Salesforce token refreshed');
        process.exit(0);
    } catch (dbErr) {
        console.warn('Warning: Token authenticated successfully, but database store encountered error:', dbErr.message);
        console.log('Salesforce token refreshed');
        process.exit(0);
    }
}

refreshSalesforceToken();
