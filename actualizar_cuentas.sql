USE `4673499_biblioteca`;

ALTER TABLE usuarios
  ADD COLUMN correo VARCHAR(150) NULL,
  ADD UNIQUE KEY uq_usuarios_correo (correo);

UPDATE usuarios SET correo = 'lector@correo.com' WHERE usuario = 'lector';
UPDATE usuarios SET correo = 'ana@correo.com' WHERE usuario = 'ana';

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
