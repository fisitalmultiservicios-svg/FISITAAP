"""ZIP permissions for shared hosting, independent of the builder's umask."""
import pathlib, stat, zipfile

def write_portable_zip(source, destination):
    source = pathlib.Path(source)
    destination = pathlib.Path(destination)
    paths = sorted(source.rglob('*'))
    with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in paths:
            if path.is_symlink():
                raise ValueError('Symlinks are not allowed in hosting packages.')
            if not path.is_dir() and not path.is_file():
                raise ValueError('Unsupported package entry.')
            name = path.relative_to(source).as_posix()
            is_directory = path.is_dir()
            entry = zipfile.ZipInfo(name + ('/' if is_directory else ''),
                                    date_time=(2026, 10, 14, 0, 0, 0))
            entry.create_system = 3
            entry.compress_type = zipfile.ZIP_DEFLATED
            mode = (stat.S_IFDIR | 0o755) if is_directory else (stat.S_IFREG | 0o644)
            entry.external_attr = (mode << 16) | (0x10 if is_directory else 0)
            archive.writestr(entry, b'' if is_directory else path.read_bytes(),
                             compresslevel=9)
    with zipfile.ZipFile(destination) as archive:
        assert archive.testzip() is None
        for entry in archive.infolist():
            assert entry.create_system == 3
            assert ((entry.external_attr >> 16) & 0o777) == (0o755 if entry.is_dir() else 0o644)

