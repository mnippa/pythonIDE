-- Migration 061: Team member overall lab evaluation status

CREATE TABLE IF NOT EXISTS team_member_lab_evaluations (
  user_id INT(10) UNSIGNED NOT NULL,
  status ENUM('durchfuehrung','bewertung','bestanden','nachpruefung','nicht_bestanden','nicht_teilgenommen') NOT NULL DEFAULT 'durchfuehrung',
  updated_by INT(10) UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_tmlab_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tmlab_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
