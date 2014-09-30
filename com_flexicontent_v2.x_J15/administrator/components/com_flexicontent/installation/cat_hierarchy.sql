DELIMITER $$
CREATE FUNCTION hierarchy_connect_by_iscycle(node INT) RETURNS INT
NOT DETERMINISTIC
READS SQL DATA
BEGIN
        DECLARE _id INT;
        DECLARE _loop INT;
        DECLARE _node INT;
        DECLARE EXIT HANDLER FOR NOT FOUND RETURN 0;
        SET _id = COALESCE(node, @id);
        SET _loop = 0;
        SET _node = 0;
        LOOP
                SELECT  parent_id
                INTO    _id
                FROM    jos_categories
                WHERE   id = _id;
                IF _id = @start_with THEN
                        SET _loop := _loop + 1;
                END IF;
                IF _id = COALESCE(node, @id) THEN
                        SET _node = _node + 1;
                END IF;
                IF _loop >= 2 THEN
                        RETURN _node;
                END IF;
        END LOOP;
END$$

DELIMITER $$
CREATE FUNCTION hierarchy_connect_by_parent_eq_prior_id_with_level_and_loop(value INT, maxlevel INT) RETURNS INT
NOT DETERMINISTIC
READS SQL DATA
BEGIN
        DECLARE _id INT;
        DECLARE _parent INT;
        DECLARE _next INT;
        DECLARE _i INT;
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET @id = NULL;

        SET _parent = @id;
        SET _id = -1;
        SET _i = 0;

        IF @id IS NULL THEN
                RETURN NULL;
        END IF;

        LOOP
                SELECT  MIN(id)
                INTO    @id
                FROM    jos_categories
                WHERE   parent_id = _parent
                        AND id > _id
                        -- Checking for @start_with in descendants
                        AND id <> @start_with
                        AND COALESCE(@level < maxlevel, TRUE);
                IF @id IS NOT NULL OR _parent = @start_with THEN
                        SET @level = @level + 1;
                        RETURN @id;
                END IF;
                SET @level := @level - 1;
                SELECT  id, parent_id
                INTO    _id, _parent
                FROM    jos_categories
                WHERE   id = _parent;
                SET _i = _i + 1;
        END LOOP;
        RETURN NULL;
END$$

DELIMITER $$
CREATE FUNCTION hierarchy_sys_connect_by_path(`delimiter` TEXT, node INT) RETURNS TEXT
NOT DETERMINISTIC
READS SQL DATA
BEGIN
   DECLARE _path TEXT;
   DECLARE _cpath TEXT;
   DECLARE _id INT;
   DECLARE EXIT HANDLER FOR NOT FOUND RETURN _path;
   SET _id = COALESCE(node, @id);
      SET _path = _id;
   LOOP
   
      SELECT  parent_id
      INTO    _id
      FROM    jos_categories
      WHERE   id = _id
         AND COALESCE(id <> @start_with, TRUE);
        SET _path = CONCAT(_id, delimiter, _path);
      END LOOP;
END$$

SELECT  CONCAT(REPEAT('    ', lvl - 1), hi.id) AS treeitem,
        hierarchy_sys_connect_by_path('/', hi.id) AS path,
        parent_id, lvl,
        CASE
            WHEN lvl >= @maxlevel THEN 1
            ELSE COALESCE(
            (
            SELECT  0
            FROM    jos_categories hl
            WHERE   hl.parent_id = ho.id
                    AND hl.id <> @start_with
            LIMIT 1
            ), 1)
        END AS is_leaf,
        hierarchy_connect_by_iscycle(hi.id) AS is_cycle
FROM    (
        SELECT  hierarchy_connect_by_parent_eq_prior_id_with_level_and_loop(id, @maxlevel) AS id,
                CAST(@level AS SIGNED) AS lvl
        FROM    (
                SELECT  @start_with := 1,
                        @id := @start_with,
                        @level := 0,
                        @maxlevel := NULL
                ) vars, jos_categories
        WHERE   @id IS NOT NULL
        ) ho
JOIN    jos_categories hi
ON      hi.id = ho.id
