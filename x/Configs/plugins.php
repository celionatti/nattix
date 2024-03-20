<?php

declare(strict_types=1);

use X\X;

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

function getPluginFiles($pluginPath): array
{
    $files = scandir($pluginPath);
    $result = [];

    foreach ($files as $file) {
        $filePath = $pluginPath . DIRECTORY_SEPARATOR . $file;

        if (is_file($filePath)) {
            $result[] = $file;
        }
    }

    return $result;
}

function getPluginFolders($pluginPath): array
{
    $folders = scandir($pluginPath);
    $result = [];

    foreach ($folders as $folder) {
        $folderPath = $pluginPath . DIRECTORY_SEPARATOR . $folder;

        if ($folder != '.' && $folder != '..' && is_dir($folderPath)) {
            $result[] = $folder;
        }
    }

    return $result;
}

/**
 * @throws Exception
 */
function loadPluginFolders($pluginsFolder = 'plugins/', $filter = null, $includeInfo = false, $requiredFolders = []): array
{
    $pathResolver = X::$x->pathResolver;
    $result = [];

    // Ensure the plugins folder path ends with a directory separator
    $pluginsFolder = rtrim($pluginsFolder, DIRECTORY_SEPARATOR);

    $folders = scandir($pathResolver->resolve() . $pluginsFolder);

    if (!$folders) {
        throw new Exception("Folders Not Found");
    }

    foreach ($folders as $folder) {
        $folderPath = $pathResolver->resolve() . $pluginsFolder . $folder;

        if ($folder != '.' && $folder != '..' && is_dir($folderPath)) {
            // Check if the plugin meets the filtering criteria
            if ($filter === null || call_user_func($filter, $folderPath)) {
                $pluginInfo = [
                    'name' => $folder,
                    'path' => $folderPath,
                    'files' => [],
                    'folders' => [],
                ];

                // Include additional information about the plugin if requested
                if ($includeInfo) {
                    $pluginInfo['files'] = getPluginFiles($folderPath);
                    $pluginInfo['folders'] = getPluginFolders($folderPath);

                    // Check if the required folders are present in the package
                    $missingRequiredFolders = array_diff($requiredFolders, $pluginInfo['folders']);
                    if (!empty($missingRequiredFolders)) {
                        $missingFoldersList = implode(', ', $missingRequiredFolders);
                        throw new Exception("Error: Missing required folder(s) in package '$folder': $missingFoldersList");
                    }
                }

                $result[] = $pluginInfo;
            }
        }
    }

    return $result;
}

function getDatabasePlugins($pluginsFolder): array
{
    $db = X::$x->database;
    $pathResolver = X::$x->pathResolver;
    $data = [];
    $db->setFetchType(PDO::FETCH_ASSOC);
    $pluginsData = $db->queryAndFetch("SELECT * FROM plugins WHERE status = :status", ['status' => 'active']);

    if ($pluginsData) {
        foreach ($pluginsData as $pluginData) {
            $dbPluginPath = $pathResolver->resolve() . $pluginsFolder . $pluginData['name'];
            $pluginStatus = $pluginData['status'];

            if (!file_exists($dbPluginPath)) {
                mkdir($dbPluginPath, 0755, true);
            }

            $data[$pluginData['name']] = $pluginStatus;
        }
    }

    return $data;
}

/**
 * @throws Exception
 */
function loadPlugins($pluginsFolder = 'plugins/', $filter = null, $includeInfo = false, $requiredFolders = []): array
{
    $plugins = loadPluginFolders($pluginsFolder, $filter, $includeInfo, $requiredFolders);
    $loadedPlugins = [];
    $existingIds = [];

    foreach ($plugins as $plugin) {
        $installJsonPath = $plugin['path'] . DIRECTORY_SEPARATOR . 'install.json';

        // Check if install.json exists for the package
        if (file_exists($installJsonPath)) {
            // Read and decode install.json
            $installJsonContent = file_get_contents($installJsonPath);
            $installData = json_decode($installJsonContent, true);

            // Check if JSON decoding was successful
            if (json_last_error() === JSON_ERROR_NONE) {
                // Check for the 'active' property
                if (isset($installData['active']) && $installData['active'] !== true) {
                    continue; // Skip inactive plugin
                }

                // Check if required fields are not empty
                $requiredFields = ['version', 'name', 'author', 'id'];
                foreach ($requiredFields as $field) {
                    if (empty($installData[$field])) {
                        throw new Exception("Error: '$field' is empty in install.json for package '{$plugin['name']}'");
                    }
                }

                // Check if the version follows the format x.y.z
                $versionPattern = '/^\d+\.\d+\.\d+$/';
                if (!preg_match($versionPattern, $installData['version'])) {
                    throw new Exception("Error: Invalid version format in install.json for package '{$plugin['name']}'. The version must follow the format x.y.z, where x, y, and z are non-negative integers");
                }

                // Check for uniqueness of id values
                if (in_array($installData['id'], $existingIds)) {
                    throw new Exception("Error: Duplicate id '{$installData['id']}' found in install.json for package '{$plugin['name']}'");
                }

                // Check dependencies
                if (!empty($installData['dependencies'])) {
                    foreach ($installData['dependencies'] as $dependency => $requiredVersion) {
                        // Check if the required dependency exists
                        $dependencyExists = false;
                        foreach ($loadedPlugins as $loadedPlugin) {
                            if ($loadedPlugin['install_data']['name'] === $dependency) {
                                $dependencyExists = true;

                                // Check if the version matches the required version
                                $loadedVersion = $loadedPlugin['install_data']['version'] ?? null;
                                if ($loadedVersion && version_compare($loadedVersion, $requiredVersion, '<')) {
                                    throw new Exception("Error: Package '{$plugin['name']}' requires version $requiredVersion or higher of '$dependency', but loaded version is $loadedVersion");
                                }

                                break;
                            }
                        }

                        // Throw an error if the required dependency is not loaded
                        if (!$dependencyExists) {
                            throw new Exception("Error: Package '{$plugin['name']}' requires '$dependency', but it is not loaded");
                        }
                    }
                }

                // Add the loaded plugin to the result array with named keys
                $uniqueId = uniqid('plugin_');
                $loadedPlugins[$uniqueId] = [
                    'plugin_info' => $plugin,
                    'install_data' => $installData,
                    'unique_id' => $uniqueId,
                ];

                // Add the id to the existing ids array
                $existingIds[] = $installData['id'];
            } else {
                // Handle JSON decoding error
                // You might want to log an error or handle it in a way suitable for your application
                throw new Exception("Error decoding install.json for package '{$plugin['name']}': " . json_last_error_msg());
            }
        }
    }

    // Sort loaded plugins based on their index value
    usort($loadedPlugins, function ($a, $b) {
        return $a['install_data']['index'] <=> $b['install_data']['index'];
    });

    // Include required plugin files in the sorted order
    foreach ($loadedPlugins as $loadedPlugin) {
        $pluginFilePath = $loadedPlugin['plugin_info']['path'] . DIRECTORY_SEPARATOR . 'plugin.php';
        if (file_exists($pluginFilePath)) {
            require_once $pluginFilePath;
        } else {
            throw new Exception("Error: Plugin file 'plugin.php' not found in package '{$loadedPlugin['plugin_info']['name']}'");
        }
    }

    return $loadedPlugins;
}