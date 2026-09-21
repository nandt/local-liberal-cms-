function lftp {
    SCRIPT_PATH="$(cd "$(dirname "$0")" || exit; pwd)"

    if [ -n "${FTP_HOST}" ] && [ -n "${FTP_USER}" ] && [ -n "${FTP_PASSWORD}" ]; then
        docker run -it -v "$SCRIPT_PATH/backup.liberal.gr:/backup.liberal.gr" minidocks/lftp -c "open ftp://$FTP_HOST; set ftp:ssl-allow no; user $FTP_USER $FTP_PASSWORD; $*"
    else
        echo "Required variable FTP_HOST, FTP_USER or FTP_PASSWORD is missing"
    fi
}