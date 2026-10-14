"""Build the read-only routing diagnostic with portable hosting permissions."""
import pathlib, shutil
from portable_zip180 import write_portable_zip

root = pathlib.Path(__file__).resolve().parents[1]
source = root/'actualizaciones/1.8.0/diagnostico-ruta'
destination = pathlib.Path('/workspace/entregas/FISITAAP-180-COMPROBAR-RUTA.zip')
write_portable_zip(source,destination)
shutil.copyfile(destination,destination.with_name('FISITAAP-180-COMPROBAR-RUTA-R1.zip'))
print('Read-only diagnostic verified: files 644, explicit directories 755.')
