import jsforce from 'jsforce';

/**
 * Sanitize error message to avoid credential leaks
 */
function sanitizeErrorMessage(err, loginUrl, username, password) {
    const rawMsg = err.message || String(err);
    let friendlyMsg = rawMsg;

    if (rawMsg.includes('INVALID_LOGIN') || rawMsg.includes('authentication failure')) {
        friendlyMsg = 'Salesforce authentication failed: Invalid username, password, or security token. Ensure SF_PASSWORD contains your password immediately followed by your Salesforce Security Token.';
    } else if (rawMsg.includes('LOGIN_MUST_USE_SECURITY_TOKEN')) {
        friendlyMsg = 'Salesforce requires a Security Token: Append your Salesforce security token to SF_PASSWORD in your .env file.';
    } else if (rawMsg.includes('INVALID_OPERATION_WITH_EXPIRED_PASSWORD') || rawMsg.includes('PASSWORD_EXPIRED')) {
        friendlyMsg = 'Salesforce password has expired. Please log into Salesforce directly and reset your password.';
    } else if (rawMsg.includes('ENOTFOUND') || rawMsg.includes('ECONNREFUSED') || rawMsg.includes('ETIMEDOUT') || rawMsg.includes('fetch failed')) {
        friendlyMsg = `Unable to connect to Salesforce login endpoint (${loginUrl || 'https://login.salesforce.com'}). Please check your network connection and SF_LOGIN_URL.`;
    }

    if (username) friendlyMsg = friendlyMsg.split(username).join('***');
    if (password) friendlyMsg = friendlyMsg.split(password).join('***');

    return friendlyMsg;
}

async function main() {
    const loginUrl = process.env.SF_LOGIN_URL || 'https://login.salesforce.com';
    const username = process.env.SF_USERNAME || '';
    const password = process.env.SF_PASSWORD || '';

    if (!username.trim() || !password.trim()) {
        console.log(JSON.stringify({
            success: false,
            error: 'Salesforce credentials are not configured. Please set SF_USERNAME and SF_PASSWORD in your .env file.',
            errorCode: 'CREDENTIALS_MISSING',
            httpCode: 400
        }));
        process.exit(0);
    }

    try {
        const conn = new jsforce.Connection({
            loginUrl: loginUrl.trim(),
            version: '61.0'
        });

        const userInfo = await conn.login(username.trim(), password.trim());

        const serverUrl = `${conn.instanceUrl}/services/Soap/u/61.0/${userInfo.organizationId}`;
        const maskedSessionId = conn.accessToken
            ? `${conn.accessToken.substring(0, 20)}********************`
            : '';

        console.log(JSON.stringify({
            success: true,
            httpCode: 200,
            username: username.trim(),
            organizationId: userInfo.organizationId,
            userId: userInfo.id,
            instanceUrl: conn.instanceUrl,
            serverUrl: serverUrl,
            maskedSessionId: maskedSessionId,
            apiVersion: '61.0',
            loginUrl: loginUrl.trim()
        }));
    } catch (err) {
        const friendlyError = sanitizeErrorMessage(err, loginUrl, username, password);
        console.log(JSON.stringify({
            success: false,
            httpCode: 401,
            error: friendlyError,
            errorCode: err.name || 'SALESFORCE_ERROR',
            rawError: String(err.message || err)
        }));
    }
}

main().catch(err => {
    console.log(JSON.stringify({
        success: false,
        httpCode: 500,
        error: 'Unexpected server-side error: ' + (err.message || String(err)),
        errorCode: 'UNEXPECTED_ERROR'
    }));
});
