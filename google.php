<?php
require "assets/init.php";
require "./assets/libs/google/vendor/autoload.php";

$client = new Google_Client();
$client->setClientId($music->config->google_ClientId);
$client->setClientSecret($music->config->google_ClientSecret);

if (isset($_GET["code"])) {
    $client->setRedirectUri($site_url . "/google.php");
    $client->setScopes(["https://www.googleapis.com/auth/drive.file"]);
    $client->setAccessType("offline");
    $client->setApprovalPrompt("force");
    $client->authenticate($_GET["code"]);
    $refreshToken = $client->getRefreshToken();
    if (!empty($refreshToken)) {
        $db->where("name", "google_refreshToken")->update(T_CONFIG, [
            "value" => $refreshToken,
        ]);
        header("Location: $site_url/admin-cp/s3?drive=success");
        exit();
    } else {
        $actual_link = $site_url . "/google.php";
        exit(
            "Error found, can't retrieve the refresh token, make sure you have Google Drive API enabled in Google Console, and the redirect URI matches with: <b>$actual_link</b>, <br> <a href='$site_url/admin-cp/s3?drive=fail'>Go Back</a>"
        );
    }
} elseif (isset($_GET["upload"])) {
    if (!isAdmin()) {
        exit("Access deined, you are not admin.");
    }
    $getAccessToken = $client->refreshToken(
        $music->config->google_refreshToken
    );
    $client->setAccessToken($getAccessToken);
    $service = new Google_Service_Drive($client);
    $addedFiles = getFilesShouldUpload();
    ob_end_clean();
    header("Content-Encoding: none");
    header("Connection: close");
    ignore_user_abort();
    ob_start();
    if (count($addedFiles) > 0) {
        echo "<h2> " .
            count($addedFiles) .
            " files will be uploaded to google drive, this may take few hours. " .
            "<a href='$site_url/admin-cp/s3?drive=fail'>Go Back</a>" .
            "</h2>";
    } else {
        echo "<h2>Great! All files are already uploaded. " .
            "<a href='$site_url/admin-cp/s3?drive=fail'>Go Back</a>" .
            "</h2>";
    }
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush();
    flush();
    session_write_close();
    if (is_callable("fastcgi_finish_request")) {
        fastcgi_finish_request();
    }
    $addedGoogleFiles = [];
    foreach ($addedFiles as $key => $lastFile) {
        $folders = explode("/", $lastFile["file"]);
        $originalFileName = $folders[4];
        $checkIfFolderExists = check_folder_exists($client, "deepsound-files");
        if (empty($checkIfFolderExists[0]["id"])) {
            $createFolder = create_folder($client, "deepsound-files");
            $createFolder = create_folder(
                $client,
                $folders[1] . "_" . $folders[2] . "_" . $folders[3],
                $createFolder
            );
        } else {
            $createFolder = create_folder(
                $client,
                $folders[1] . "_" . $folders[2] . "_" . $folders[3],
                $checkIfFolderExists[0]["id"]
            );
        }
        $file = new Google_Service_Drive_DriveFile();
        $file->setName($originalFileName);
        $file->setParents([$createFolder]);
        $createdFile = $service->files->create($file, [
            "data" => file_get_contents($lastFile["file"]),
        ]);
        $permissionService = new Google_Service_Drive_Permission();
        $permissionService->role = "reader";
        $permissionService->type = "anyone"; // anyone with the link can view the file
        $service->permissions->create($createdFile->id, $permissionService);
        if (!empty($createdFile->id)) {
            $db->where("id", $lastFile["id"])->update($lastFile["table"], [
                $lastFile["col"] => $createdFile->id,
            ]);
            @unlink($lastFile["file"]);
            $addedGoogleFiles[] = $createdFile->id;
        }
    }
} elseif (isset($_GET["download"])) {
    if (!isAdmin()) {
        exit("Access deined, you are not admin.");
    }

    $getAccessToken = $client->refreshToken(
        $music->config->google_refreshToken
    );
    $client->setAccessToken($getAccessToken);
    $service = new Google_Service_Drive($client);
    $addedFiles = getFilesShouldDownload();
    ob_end_clean();
    header("Content-Encoding: none");
    header("Connection: close");
    ignore_user_abort();
    ob_start();
    if (count($addedFiles) > 0) {
        echo "<h2> " .
            count($addedFiles) .
            " files will be downloaded to server, this may take few hours. " .
            "<a href='$site_url/admin-cp/s3?drive=fail'>Go Back</a>" .
            "</h2>";
    } else {
        echo "<h2>Great! All files are already downloaded. " .
            "<a href='$site_url/admin-cp/s3?drive=fail'>Go Back</a>" .
            "</h2>";
    }
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush();
    flush();
    session_write_close();
    if (is_callable("fastcgi_finish_request")) {
        fastcgi_finish_request();
    }

    $addedGoogleFiles = [];
    foreach ($addedFiles as $key => $lastFile) {
        try {
            $response = $service->files->get($lastFile["file"], [
                "fields" => "parents,name",
            ]);
            $fileName = $response->name;
            $getFolderName = $service->files->get($response->parents, [
                "fields" => "name",
            ]);
            $getFolderNameRespond = $getFolderName->name;
            if (!empty($getFolderNameRespond)) {
                $getFolderPath = explode("_", $getFolderNameRespond);
                $createNewFolderPath = implode("/", $getFolderPath);
                createPath("upload/" . $createNewFolderPath);
                $response = $service->files->get($lastFile["file"], [
                    "alt" => "media",
                ]);
                $finalPath = "upload/" . $createNewFolderPath . "/" . $fileName;
                if (!file_exists($finalPath)) {
                    $filePut = file_put_contents(
                        $finalPath,
                        $response->getBody()
                    );
                    if ($filePut) {

                        $deleteFile = $service->files->delete(
                            $lastFile["file"]
                        );
                    }
                }
                $db->where("id", $lastFile["id"])->update(
                    $lastFile["table"],
                    [$lastFile["col"] => $finalPath]
                );
            }
        } catch (Exception $e) {
        }
    }
} else {
    exit("No code parameter was passed, bad request.");
}
?>
