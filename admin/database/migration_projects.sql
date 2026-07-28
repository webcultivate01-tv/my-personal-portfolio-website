-- ============================================================
--  Project Management module — existing install upgrade.
--  Run this once in phpMyAdmin. Fresh installs get these tables
--  automatically from schema.sql.
-- ============================================================

-- A project the team is working on. Optionally linked to a client record
-- (so "which client is this for" is answerable), but not required — internal
-- or prospective work can live here without a client yet.
CREATE TABLE IF NOT EXISTS projects (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150) NOT NULL,
  client_id    INT UNSIGNED NULL,
  description  TEXT         NULL,
  status       ENUM('planning','in_progress','on_hold','completed','cancelled') NOT NULL DEFAULT 'planning',
  priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  start_date   DATE         NULL,
  due_date     DATE         NULL,
  budget       DECIMAL(12,2) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projects_status (status),
  KEY idx_projects_client (client_id),
  CONSTRAINT fk_projects_client     FOREIGN KEY (client_id)  REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A unit of work inside a project, assigned to one team member.
--   status   : todo -> in_progress -> review -> done, moved by an admin or
--              whoever the task is assigned to.
CREATE TABLE IF NOT EXISTS project_tasks (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id   INT UNSIGNED NOT NULL,
  title        VARCHAR(200) NOT NULL,
  description  TEXT         NULL,
  assigned_to  INT UNSIGNED NULL,
  status       ENUM('todo','in_progress','review','done') NOT NULL DEFAULT 'todo',
  priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  due_date     DATE         NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_tasks_project  (project_id),
  KEY idx_project_tasks_assigned (assigned_to),
  KEY idx_project_tasks_status   (status),
  CONSTRAINT fk_project_tasks_project    FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_tasks_assigned   FOREIGN KEY (assigned_to) REFERENCES users(id)    ON DELETE SET NULL,
  CONSTRAINT fk_project_tasks_created_by FOREIGN KEY (created_by)  REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments on a task — a running timeline (like lead_notes), so the admin
-- and the developer it's assigned to can leave updates without overwriting
-- each other, and every entry keeps its author + time.
CREATE TABLE IF NOT EXISTS project_task_notes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  note       TEXT         NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_task_notes_task (task_id),
  CONSTRAINT fk_project_task_notes_task FOREIGN KEY (task_id) REFERENCES project_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_task_notes_user FOREIGN KEY (user_id) REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
