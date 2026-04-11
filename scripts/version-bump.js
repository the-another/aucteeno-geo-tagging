#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Configuration
const MAIN_PLUGIN_FILE = 'aucteeno-geo-tagging.php';
const VERSION_CONSTANT_NAME = 'AUCTEENO_GEO_TAGGING_VERSION';

// Get version type argument (patch, minor, major)
const versionType = process.argv[2];
const validTypes = ['patch', 'minor', 'major'];

// Load package.json
const packageJsonPath = path.join(__dirname, '../package.json');
const packageJson = require(packageJsonPath);
let newVersion = packageJson.version;

// If version type is provided, increment the version
if (versionType && validTypes.includes(versionType)) {
  const [major, minor, patch] = newVersion.split('.').map(Number);

  switch (versionType) {
    case 'major':
      newVersion = `${major + 1}.0.0`;
      break;
    case 'minor':
      newVersion = `${major}.${minor + 1}.0`;
      break;
    case 'patch':
      newVersion = `${major}.${minor}.${patch + 1}`;
      break;
  }

  // Update package.json with new version
  packageJson.version = newVersion;
  fs.writeFileSync(packageJsonPath, JSON.stringify(packageJson, null, 4) + '\n', 'utf8');
  console.log(`✓ Bumped version to ${newVersion} (${versionType})`);
} else if (versionType) {
  console.error(`Invalid version type: ${versionType}. Use: patch, minor, or major`);
  process.exit(1);
}

console.log(`Updating version to ${newVersion}...`);

// Update composer.json if it has a version field
const composerJsonPath = path.join(__dirname, '../composer.json');
if (fs.existsSync(composerJsonPath)) {
  const composerJson = JSON.parse(fs.readFileSync(composerJsonPath, 'utf8'));
  if (composerJson.version !== undefined) {
    composerJson.version = newVersion;
    fs.writeFileSync(composerJsonPath, JSON.stringify(composerJson, null, 4) + '\n', 'utf8');
    console.log('✓ Updated composer.json');
  }
}

// Update main plugin file: Version: header and version constant.
const pluginFile = path.join(__dirname, '..', MAIN_PLUGIN_FILE);
let pluginContent = fs.readFileSync(pluginFile, 'utf8');

pluginContent = pluginContent.replace(
  /(\* Version:\s+)[\d.]+/,
  `$1${newVersion}`
);

pluginContent = pluginContent.replace(
  new RegExp(`(define\\(\\s*'${VERSION_CONSTANT_NAME}',\\s*')[\\d.]+('\\s*\\);)`),
  `$1${newVersion}$2`
);

fs.writeFileSync(pluginFile, pluginContent, 'utf8');
console.log(`✓ Updated ${MAIN_PLUGIN_FILE}`);

// Update readme.txt: Stable tag + prepend changelog entry.
const readmeFile = path.join(__dirname, '../readme.txt');
if (fs.existsSync(readmeFile)) {
  let readmeContent = fs.readFileSync(readmeFile, 'utf8');

  readmeContent = readmeContent.replace(
    /(Stable tag:\s+)[\d.]+/,
    `$1${newVersion}`
  );

  // Prepend a new changelog entry under == Changelog ==.
  const today = new Date().toISOString().split('T')[0];
  const changelogEntry = `= ${newVersion} - ${today} =\n* Version bump\n\n`;

  readmeContent = readmeContent.replace(
    /(== Changelog ==\s*\n)/,
    `$1\n${changelogEntry}`
  );

  fs.writeFileSync(readmeFile, readmeContent, 'utf8');
  console.log('✓ Updated readme.txt');
}

console.log(`\nVersion ${newVersion} update complete!`);
