/** SEPARATOR **/
CREATE TABLE IF NOT EXISTS ajxp_mail_queue (
 id serial PRIMARY KEY,
 recipient varchar(255) NOT NULL,
 url text NOT NULL,
 date_event integer NOT NULL,
 notification_object bytea NOT NULL,
 html integer NOT NULL
);
/** SEPARATOR **/
CREATE TABLE IF NOT EXISTS ajxp_mail_sent (
 id serial PRIMARY KEY,
 recipient varchar(255) NOT NULL,
 url text NOT NULL,
 date_event integer NOT NULL,
 notification_object bytea NOT NULL,
 html integer NOT NULL
);
/** SEPARATOR **/
/** BLOCK **/
CREATE FUNCTION ajxp_send_mail() RETURNS trigger AS $ajxp_send_mail$
    BEGIN
        INSERT INTO ajxp_mail_sent (recipient,url,date_event,notification_object,html)
            VALUES (OLD.recipient,OLD.url,OLD.date_event,OLD.notification_object,OLD.html);
        RETURN OLD;
    END;
$ajxp_send_mail$ LANGUAGE plpgsql;
/** SEPARATOR **/
CREATE TRIGGER mail_queue_go_to_sent BEFORE DELETE ON ajxp_mail_queue
FOR EACH ROW EXECUTE PROCEDURE ajxp_send_mail();
