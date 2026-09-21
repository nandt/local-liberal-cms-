-- This function searches the txt provided for numeric HTML codes (e.g. &#39;) and replaces it with the actual character
-- Shamelessly stolen from here: https://stackoverflow.com/a/36343632/3061050
CREATE FUNCTION entity_decode(txt LONGTEXT CHARSET utf8) RETURNS LONGTEXT CHARSET utf8
    NO SQL
    DETERMINISTIC
BEGIN
    DECLARE tmp LONGTEXT CHARSET utf8 DEFAULT txt;
    DECLARE entity  LONGTEXT CHARSET utf8;
    DECLARE pos1    INT DEFAULT 1;
    DECLARE pos2    INT;
    DECLARE codepoint   INT;
    IF txt IS NULL THEN
        RETURN NULL;
    END IF;
    LOOP
        SET pos1 = LOCATE('&#', tmp, pos1);
        IF pos1 = 0 THEN
            RETURN tmp;
        END IF;
        SET pos2 = LOCATE(';', tmp, pos1 + 2);
        IF pos2 > pos1 THEN
            SET entity = SUBSTRING(tmp, pos1, pos2 - pos1 + 1);
            IF entity REGEXP '^&#[[:digit:]]+;$' THEN
                SET codepoint = CAST(SUBSTRING(entity, 3, pos2 - pos1 - 2) AS UNSIGNED);
                IF codepoint > 31 THEN
                    SET tmp = CONCAT(LEFT(tmp, pos1 - 1), CHAR(codepoint USING utf32), SUBSTRING(tmp, pos2 + 1));
                END IF;
            END IF;
            IF entity REGEXP '^&#x[[:digit:]]+;$' THEN
                SET codepoint = CAST(CONV(SUBSTRING(entity, 4, pos2 - pos1 - 3), 16, 10) AS UNSIGNED);
                IF codepoint > 31 THEN
                    SET tmp = CONCAT(LEFT(tmp, pos1 - 1), CHAR(codepoint USING utf32), SUBSTRING(tmp, pos2 + 1));
                END IF;
            END IF;
        END IF;
        SET pos1 = pos1 + 1;
    END LOOP;
END

-- After the function is created the following UPDATEs need to be performed
UPDATE lib_node__body
SET body_value = entity_decode(body_value);
UPDATE lib_node__body
SET body_summary = entity_decode(body_summary);
UPDATE lib_node__field_title
SET field_title_value = entity_decode(field_title_value);
UPDATE lib_node_field_data
SET title = entity_decode(title);
UPDATE lib_node_field_revision
SET title = entity_decode(title);
UPDATE lib_node_revision__body
SET body_value = entity_decode(body_value);
UPDATE lib_node_revision__body
SET body_summary = entity_decode(body_summary);
UPDATE lib_node_revision__field_title
SET field_title_value = entity_decode(field_title_value);