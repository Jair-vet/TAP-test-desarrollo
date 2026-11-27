// Se ejecuta UNA vez al crear el volumen de Mongo.
// Crea el usuario de la aplicación con privilegios SOLO sobre la BD `tap`
// (principio de mínimo privilegio: la API nunca usa el usuario root).
const dbName = process.env.MONGO_INITDB_DATABASE || 'tap';
db.getSiblingDB(dbName).createUser({
  user: 'tap_app',
  pwd: process.env.MONGO_APP_PASSWORD,
  roles: [
    { role: 'readWrite', db: dbName }, // CRUD + crear colecciones/índices
    { role: 'dbAdmin', db: dbName },   // collMod (validadores $jsonSchema) en migraciones
  ],
});
print(`Usuario tap_app creado sobre ${dbName}`);
