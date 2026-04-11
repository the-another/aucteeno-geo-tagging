#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Load package.json to get name and version
const packageJsonPath = path.join(__dirname, '../package.json');
const packageJson = require(packageJsonPath);
const packageName = packageJson.name;
const version = packageJson.version;

// Define paths
const rootDir = path.join(__dirname, '/../');
const buildDir = path.join(rootDir, 'build');
const sourceZip = path.join(rootDir, `${packageName}.zip`);
const versionedZip = path.join(buildDir, `${packageName}-${version}.zip`);
const latestZip = path.join(buildDir, `${packageName}.zip`);

// Check if source zip exists
if (!fs.existsSync(sourceZip)) {
  console.error(`Error: ${sourceZip} not found`);
  process.exit(1);
}

// Ensure build directory exists
fs.mkdirSync(buildDir, { recursive: true });

// Move source zip to versioned zip (historical)
fs.renameSync(sourceZip, versionedZip);
console.log(`✓ Created ${path.basename(versionedZip)} in build directory`);

// Copy versioned zip to latest zip
fs.copyFileSync(versionedZip, latestZip);
console.log(`✓ Created ${path.basename(latestZip)} in build directory (latest)`);
