CREATE DATABASE IF NOT EXISTS imcb_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE imcb_portal;

CREATE TABLE programs (id INT AUTO_INCREMENT PRIMARY KEY, level ENUM('HSSC','BS','ADP') NOT NULL, name VARCHAR(150) NOT NULL, specialization VARCHAR(200) NULL, active TINYINT(1) DEFAULT 1);
CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(160) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, role ENUM('admin','teacher','student') NOT NULL, status ENUM('active','inactive') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE sections (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(30) NOT NULL, program_id INT NOT NULL, term VARCHAR(50) NOT NULL, academic_year VARCHAR(20) NOT NULL, UNIQUE(program_id,name,term,academic_year), FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE CASCADE);
CREATE TABLE students (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT UNIQUE NOT NULL, roll_no VARCHAR(50) UNIQUE NOT NULL, program_id INT NOT NULL, semester_or_year VARCHAR(30) NOT NULL, section_id INT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(program_id) REFERENCES programs(id), FOREIGN KEY(section_id) REFERENCES sections(id) ON DELETE SET NULL);
CREATE TABLE teachers (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT UNIQUE NOT NULL, department VARCHAR(120), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE subjects (id INT AUTO_INCREMENT PRIMARY KEY, program_id INT NOT NULL, code VARCHAR(30), name VARCHAR(150) NOT NULL, semester VARCHAR(30) NULL, credit_hours DECIMAL(3,1) DEFAULT 3, FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE CASCADE);
CREATE TABLE enrollments (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, subject_id INT NOT NULL, UNIQUE(student_id,subject_id), FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE);
CREATE TABLE attendance (id INT AUTO_INCREMENT PRIMARY KEY, enrollment_id INT NOT NULL, attendance_date DATE NOT NULL, status ENUM('present','absent','leave') NOT NULL, FOREIGN KEY(enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE);
CREATE TABLE marks (id INT AUTO_INCREMENT PRIMARY KEY, enrollment_id INT NOT NULL, assessment VARCHAR(50) NOT NULL, obtained DECIMAL(6,2) DEFAULT 0, total DECIMAL(6,2) NOT NULL, FOREIGN KEY(enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE);
CREATE TABLE notices (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, audience ENUM('all','hssc','bs','adp','students','teachers') DEFAULT 'all', published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

INSERT INTO programs(level,name) VALUES
('HSSC','Pre-Engineering'),('HSSC','Pre-Medical'),('HSSC','General Science'),('HSSC','Humanities'),('HSSC','Commerce'),
('BS','BS English'),('BS','BS Statistics','Specialization in Data Science'),('BS','BS Urdu'),
('ADP','ADP Arts'),('ADP','ADP Science');

INSERT INTO users(name,email,password_hash,role) VALUES
('Portal Administrator','admin@imcb.edu.pk', '$2y$10$3Q8Z3Qw5j7ZQ0nq3a8dJue4YgM2Hq5Q6lR9bJ0v2Y6xZ5f4dW8k3S', 'admin'),
('Demo Student','student@imcb.edu.pk', '$2y$10$w3o8y7xY4cVqfG7N2mJ5wO4rD6kF1pB8nS0uQ2zL5eA7iC9dR1tU2', 'student'),
('Demo Teacher','teacher@imcb.edu.pk', '$2y$10$9mN7vB4xC2zL8kQ5pR3tY1uI6oP0aS4dF7gH9jK2lZ5xW8cV1bN3', 'teacher');

-- Demo hashes above are placeholders for the seed login. Run the included reset_demo_passwords.php once if needed.
INSERT INTO sections(name,program_id,term,academic_year) VALUES('A',7,'5th Semester','2026-27');
INSERT INTO students(user_id,roll_no,program_id,semester_or_year,section_id) VALUES(2,'DEMO-001',7,'5th Semester',1);
INSERT IGNORE INTO enrollments(student_id,subject_id) SELECT 1,id FROM subjects WHERE program_id=7 AND (semester IS NULL OR semester='5th Semester' OR semester='');
INSERT INTO teachers(user_id,department) VALUES(3,'BS / ADP');
INSERT INTO subjects(program_id,code,name,semester,credit_hours) VALUES
(7,'STA-301','Probability & Statistics','5th Semester',3),(7,'STA-302','Statistical Inference','5th Semester',3),(7,'DS-301','Python for Data Science','5th Semester',3),(7,'DS-302','Database Systems','5th Semester',3),(7,'DS-303','Machine Learning','5th Semester',3),(7,'DS-304','Data Visualization','5th Semester',3);
INSERT IGNORE INTO enrollments(student_id,subject_id) SELECT 1,id FROM subjects WHERE program_id=7 AND semester='5th Semester';

-- Phase 6: result and grading engine
CREATE TABLE IF NOT EXISTS grade_scales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  min_percent DECIMAL(5,2) NOT NULL,
  max_percent DECIMAL(5,2) NOT NULL,
  letter_grade VARCHAR(5) NOT NULL,
  grade_point DECIMAL(3,2) NOT NULL,
  UNIQUE(min_percent,max_percent)
);
INSERT IGNORE INTO grade_scales(min_percent,max_percent,letter_grade,grade_point) VALUES
(90,100,'A+',4.00),(85,89.99,'A',4.00),(80,84.99,'B+',3.50),(75,79.99,'B',3.00),
(70,74.99,'C+',2.50),(65,69.99,'C',2.00),(60,64.99,'D',1.00),(0,59.99,'F',0.00);

-- Phase 8: teacher authorization, result publication/locking
CREATE TABLE IF NOT EXISTS teacher_subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  subject_id INT NOT NULL,
  UNIQUE(teacher_id,subject_id),
  FOREIGN KEY(teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS result_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  term VARCHAR(50) NOT NULL,
  status ENUM('draft','published','locked') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  locked_at DATETIME NULL,
  UNIQUE(student_id,term),
  FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
);
INSERT IGNORE INTO teacher_subjects(teacher_id,subject_id) SELECT t.id,s.id FROM teachers t CROSS JOIN subjects s JOIN users u ON u.id=t.user_id WHERE u.email='teacher@imcb.edu.pk';
INSERT IGNORE INTO result_status(student_id,term,status,published_at) SELECT st.id,st.semester_or_year,'published',NOW() FROM students st JOIN users u ON u.id=st.user_id WHERE u.email='student@imcb.edu.pk';

-- Phase 9: online admissions and fee management
CREATE TABLE IF NOT EXISTS admission_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_no VARCHAR(40) UNIQUE NOT NULL,
  name VARCHAR(150) NOT NULL,
  father_name VARCHAR(150) NOT NULL,
  cnic VARCHAR(30) NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NULL,
  program_id INT NOT NULL,
  previous_marks DECIMAL(10,2) NOT NULL,
  previous_total DECIMAL(10,2) NOT NULL,
  merit_score DECIMAL(6,2) NOT NULL DEFAULT 0,
  status ENUM('pending','verified','shortlisted','accepted','rejected') DEFAULT 'pending',
  remarks TEXT NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE RESTRICT,
  INDEX idx_admission_merit(merit_score), INDEX idx_admission_status(status)
);
CREATE TABLE IF NOT EXISTS fee_challans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  challan_no VARCHAR(50) UNIQUE NOT NULL,
  purpose VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  status ENUM('unpaid','paid','overdue','cancelled') DEFAULT 'unpaid',
  paid_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_fee_student(student_id), INDEX idx_fee_status(status)
);

-- Phase 10: student registration + admission conversion
ALTER TABLE admission_applications
  ADD COLUMN IF NOT EXISTS admission_session VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS document_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS document_remarks VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS student_id INT NULL,
  ADD COLUMN IF NOT EXISTS converted_at DATETIME NULL;
CREATE INDEX IF NOT EXISTS idx_admission_student ON admission_applications(student_id);

UPDATE admission_applications SET admission_session=CONCAT(YEAR(created_at),'-',RIGHT(YEAR(created_at)+1,2)) WHERE admission_session IS NULL;
-- Phase 11 migration: document management, ID cards and certificates
USE imcb_portal;

CREATE TABLE IF NOT EXISTS student_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  document_type ENUM('photo','cnic_bform','matric_certificate','inter_certificate','domicile','previous_result','other') NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NULL,
  file_size INT UNSIGNED NULL,
  status ENUM('pending','verified','rejected') DEFAULT 'pending',
  remarks VARCHAR(255) NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  verified_at DATETIME NULL,
  FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_docs_student(student_id), INDEX idx_docs_status(status)
);

CREATE TABLE IF NOT EXISTS student_certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  certificate_type ENUM('bonafide','character','enrollment') NOT NULL,
  purpose VARCHAR(255) NULL,
  certificate_no VARCHAR(60) UNIQUE NOT NULL,
  issue_date DATE NOT NULL,
  remarks VARCHAR(255) NULL,
  FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_cert_student(student_id)
);

CREATE TABLE IF NOT EXISTS student_id_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNIQUE NOT NULL,
  card_no VARCHAR(60) UNIQUE NOT NULL,
  issue_date DATE NOT NULL,
  valid_until DATE NULL,
  status ENUM('active','expired','cancelled') DEFAULT 'active',
  FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
);
