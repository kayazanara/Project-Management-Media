@extends('layouts.app')

@section('content')
    @php
        $avatarUrl = $user->avatar
            ? asset('storage/avatars/' . $user->avatar)
            : asset('sb-admin-2/img/undraw_profile.svg');
    @endphp

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-edit mr-2"></i> Edit Profil
        </h1>
        <a href="{{ route('profile.show', $user->id) }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Foto & Background</h6>
                </div>
                <div class="card-body text-center">
                    <img src="{{ $avatarUrl }}" class="rounded-circle shadow mb-3"
                         style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #4e73df;">

                    <button class="btn btn-outline-primary btn-sm btn-block" data-toggle="modal" data-target="#changeAvatarModal">
                        <i class="fas fa-camera mr-1"></i> Ganti Foto Profil
                    </button>

                    <hr>

                    <p class="text-muted small mb-2">Background Profil</p>
                    @if($user->background_image)
                        <img src="{{ asset('storage/backgrounds/' . $user->background_image) }}"
                             class="img-fluid rounded mb-2" style="max-height: 100px;">
                    @else
                        <div class="rounded mb-2" style="height: 80px; background: {{ $user->theme_gradient }};"></div>
                    @endif

                    <button class="btn btn-outline-secondary btn-sm btn-block" data-toggle="modal" data-target="#changeBgModal">
                        <i class="fas fa-image mr-1"></i> Ganti Background
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Pribadi</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('profile.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label><strong>Nama Lengkap</strong> <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>

                        <div class="form-group">
                            <label><strong>Bio Singkat</strong> <small class="text-muted">(maks 500 karakter)</small></label>
                            <textarea name="bio" class="form-control" rows="3" maxlength="500"
                                      placeholder="Ceritakan tentang dirimu...">{{ old('bio', $user->bio) }}</textarea>
                        </div>

                        <div class="form-group">
                            <label><strong>Nomor WhatsApp</strong></label>
                            <input type="text" name="phone" class="form-control"
                                   value="{{ old('phone', $user->phone) }}"
                                   placeholder="Contoh: 08123456789">
                        </div>

                        <div class="form-group">
                            <label><strong>Warna Tema Profil</strong></label>
                            <select name="theme_color" class="form-control">
                                <option value="blue-purple" {{ $user->theme_color == 'blue-purple' ? 'selected' : '' }}>Biru-Ungu</option>
                                <option value="ocean" {{ $user->theme_color == 'ocean' ? 'selected' : '' }}>Ocean</option>
                                <option value="sunset" {{ $user->theme_color == 'sunset' ? 'selected' : '' }}>Sunset</option>
                                <option value="emerald" {{ $user->theme_color == 'emerald' ? 'selected' : '' }}>Emerald</option>
                                <option value="rose" {{ $user->theme_color == 'rose' ? 'selected' : '' }}>Rose</option>
                                <option value="dark" {{ $user->theme_color == 'dark' ? 'selected' : '' }}>Dark</option>
                            </select>
                        </div>

                        <hr>
                        <h6 class="font-weight-bold mb-3">🔗 Media Sosial</h6>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label><i class="fab fa-instagram text-danger"></i> Instagram</label>
                                <input type="text" name="social_links[instagram]" class="form-control"
                                       value="{{ old('social_links.instagram', $user->social_links['instagram'] ?? '') }}"
                                       placeholder="username">
                            </div>
                            <div class="col-md-6 form-group">
                                <label><i class="fab fa-github text-dark"></i> GitHub</label>
                                <input type="text" name="social_links[github]" class="form-control"
                                       value="{{ old('social_links.github', $user->social_links['github'] ?? '') }}"
                                       placeholder="username">
                            </div>
                            <div class="col-md-6 form-group">
                                <label><i class="fab fa-facebook text-primary"></i> Facebook</label>
                                <input type="text" name="social_links[facebook]" class="form-control"
                                       value="{{ old('social_links.facebook', $user->social_links['facebook'] ?? '') }}"
                                       placeholder="username">
                            </div>
                            <div class="col-md-6 form-group">
                                <label><i class="fab fa-tiktok text-dark"></i> TikTok</label>
                                <input type="text" name="social_links[tiktok]" class="form-control"
                                       value="{{ old('social_links.tiktok', $user->social_links['tiktok'] ?? '') }}"
                                       placeholder="username">
                            </div>
                        </div>

                        <div class="text-right mt-3">
                            <a href="{{ route('profile.show', $user->id) }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changeBgModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form action="{{ route('profile.updateBackground') }}" method="POST" id="bgForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold text-primary">Ganti Background Profil</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body text-center">
                    <div class="form-group">
                        <input type="file" id="bgInput" class="form-control-file mb-3" accept="image/*">
                    </div>
                    <div id="bgCropContainer" style="display: none; max-height: 350px;">
                        <img id="bgToCrop" src="" style="max-width: 100%;">
                    </div>
                    <input type="hidden" name="cropped_background" id="croppedBgInput">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="bgSaveBtn" style="display: none;">Simpan Background</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="changeAvatarModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('profile.updateAvatar') }}" method="POST" id="cropForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold text-primary">Ganti Foto Profil</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body text-center">
                    <div class="form-group">
                        <input type="file" id="imageInput" class="form-control-file mb-3" accept="image/*">
                    </div>
                    <div class="img-container" style="display: none;">
                        <img id="imageToCrop" src="" style="max-width: 100%;">
                    </div>
                    <input type="hidden" name="cropped_avatar" id="croppedAvatarInput">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="cropAndUploadBtn" style="display: none;">Simpan Foto</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
    let avatarCropper;
    const imageInput = document.getElementById('imageInput');
    if (imageInput) {
        imageInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = document.getElementById('imageToCrop');
                img.src = ev.target.result;
                document.querySelector('.img-container').style.display = 'block';
                document.getElementById('cropAndUploadBtn').style.display = 'inline-block';
                if (avatarCropper) avatarCropper.destroy();
                avatarCropper = new Cropper(img, { aspectRatio: 1, viewMode: 1 });
            };
            reader.readAsDataURL(file);
        });
        document.getElementById('cropForm').addEventListener('submit', function (e) {
            if (avatarCropper) {
                e.preventDefault();
                const canvas = avatarCropper.getCroppedCanvas({ width: 400, height: 400 });
                document.getElementById('croppedAvatarInput').value = canvas.toDataURL('image/jpeg', 0.9);
                this.submit();
            }
        });
    }

    let bgCropper;
    const bgInput = document.getElementById('bgInput');
    if (bgInput) {
        bgInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = document.getElementById('bgToCrop');
                img.src = ev.target.result;
                document.getElementById('bgCropContainer').style.display = 'block';
                document.getElementById('bgSaveBtn').style.display = 'inline-block';
                if (bgCropper) bgCropper.destroy();
                bgCropper = new Cropper(img, { aspectRatio: 16 / 6, viewMode: 1 });
            };
            reader.readAsDataURL(file);
        });
        document.getElementById('bgForm').addEventListener('submit', function (e) {
            if (bgCropper) {
                e.preventDefault();
                const canvas = bgCropper.getCroppedCanvas({ width: 1200, height: 450 });
                document.getElementById('croppedBgInput').value = canvas.toDataURL('image/jpeg', 0.85);
                this.submit();
            }
        });
    }
</script>
@endpush