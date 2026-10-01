// Test script to help debug the GSD path issue
const fs = require('fs');
const path = require('path');

console.log('=== GSD Debug Info ===');
console.log('Current working directory:', process.cwd());
console.log('Node version:', process.version);

// Check if GSD bin directory exists
const gsdBinPath = path.join('.claude', 'get-shit-done', 'bin');
if (fs.existsSync(gsdBinPath)) {
    console.log('✓ GSD bin directory exists:', gsdBinPath);

    // Try to read the main file
    const mainFile = path.join(gsdBinPath, 'gsd-tools.cjs');
    if (fs.existsSync(mainFile)) {
        console.log('✓ GSD main file exists:', mainFile);
    } else {
        console.log('✗ GSD main file does not exist:', mainFile);
    }
} else {
    console.log('✗ GSD bin directory does not exist:', gsdBinPath);
}

// Try to run a simple Node.js command
console.log('=== Testing node module resolution ===');
try {
    const pathToCheck = require.resolve('.claude/get-shit-done/bin/gsd-tools.cjs', {
        paths: [process.cwd()]
    });
    console.log('✓ Resolved path:', pathToCheck);
} catch (err) {
    console.log('✗ Failed to resolve GSD module:', err.message);
}