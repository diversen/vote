CREATE TABLE IF NOT EXISTS vote (
    id int PRIMARY KEY AUTO_INCREMENT,
    vote varchar(255),
    reference varchar(255), 
    INDEX (reference),
    parent_id int,
    INDEX (parent_id)
);

CREATE TABLE IF NOT EXISTS vote_user (
    id int PRIMARY KEY AUTO_INCREMENT,
    ip varchar(40),
    vote int,
    user_id int,
    INDEX (user_id)
);