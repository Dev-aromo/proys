USE `4673499_biblioteca`;

DROP TABLE IF EXISTS carrito;
DROP TABLE IF EXISTS prestamos;
DROP TABLE IF EXISTS libros;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
  id_usuario  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  usuario     VARCHAR(50)  NOT NULL UNIQUE,
  correo      VARCHAR(150) NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  rol         ENUM('lector','admin') NOT NULL DEFAULT 'lector',
  creado_en   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE libros (
  id_libro    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo      VARCHAR(200) NOT NULL,
  autor       VARCHAR(150) NOT NULL,
  categoria   VARCHAR(80)  NOT NULL,
  copias      SMALLINT NOT NULL DEFAULT 1,
  color       CHAR(6) NOT NULL DEFAULT '15803D',
  portada     MEDIUMBLOB NULL,
  portada_v   INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_libros_categoria ON libros (categoria);

CREATE TABLE prestamos (
  id_prestamo       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_libro          INT UNSIGNED NOT NULL,
  id_usuario        INT UNSIGNED NOT NULL,
  fecha_salida      DATE NOT NULL,
  fecha_vence       DATE NOT NULL,
  fecha_devolucion  DATE NULL,
  estado            ENUM('activo','devuelto') NOT NULL DEFAULT 'activo',
  CONSTRAINT fk_prestamo_libro   FOREIGN KEY (id_libro)
    REFERENCES libros(id_libro)     ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_prestamo_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_prestamos_estado  ON prestamos (estado, fecha_vence);
CREATE INDEX idx_prestamos_libro   ON prestamos (id_libro, estado);
CREATE INDEX idx_prestamos_usuario ON prestamos (id_usuario, estado);

CREATE TABLE carrito (
  id_usuario   INT UNSIGNED NOT NULL,
  id_libro     INT UNSIGNED NOT NULL,
  agregado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario, id_libro),
  CONSTRAINT fk_carrito_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_carrito_libro FOREIGN KEY (id_libro)
    REFERENCES libros(id_libro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO usuarios (nombre, usuario, correo, password, rol) VALUES
  ('Administrador', 'admin',  NULL, '$2y$10$t2NF13L8JDnydGc1xGOPlekWO0nVcYSGFeKfkHnIeexibKiGIbiyO', 'admin'),
  ('Lector Demo',   'lector', 'lector@correo.com', '$2y$10$Mh7LUoujLuZUYAH8bUE18eDA11UQyvPlXVaIPEFfy7TLsmrmEozDm', 'lector'),
  ('Ana Torres',    'ana',    'ana@correo.com', '$2y$10$Mh7LUoujLuZUYAH8bUE18eDA11UQyvPlXVaIPEFfy7TLsmrmEozDm', 'lector');

INSERT INTO libros (titulo, autor, categoria, copias, color) VALUES
  ('Cien años de soledad',          'Gabriel García Márquez',   'Novela',     3, '15803D'),
  ('El principito',                 'Antoine de Saint-Exupéry', 'Infantil',   2, '166534'),
  ('Introducción a los algoritmos', 'Thomas H. Cormen',         'Tecnología', 2, '14532D'),
  ('Pedro Páramo',                  'Juan Rulfo',               'Novela',     2, '16A34A'),
  ('Clean Code',                    'Robert C. Martin',         'Tecnología', 1, '047857'),
  ('Breve historia del tiempo',     'Stephen Hawking',          'Ciencia',    2, '065F46'),
  ('El laberinto de la soledad',    'Octavio Paz',              'Ensayo',     1, '15803D'),
  ('Don Quijote de la Mancha',      'Miguel de Cervantes',      'Novela',     2, '166534');

INSERT INTO prestamos (id_libro, id_usuario, fecha_salida, fecha_vence) VALUES
  (2, 2, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
  (5, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  DATE_ADD(CURDATE(), INTERVAL 4 DAY));
