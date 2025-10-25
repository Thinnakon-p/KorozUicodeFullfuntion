<!-- CHANGE PASSWORD MODAL -->
<div id="changePasswordModal" class="fixed inset-0 hidden flex items-center justify-center z-50 bg-black bg-opacity-50">
    <div class="bg-gray-800 p-6 rounded-lg max-w-md w-full mx-4">
        <h3 class="text-2xl font-bold mb-4 text-red-500">เปลี่ยนรหัสผ่าน</h3>
        <form id="changePasswordForm">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" id="changePasswordUserId" name="user_id">
            <input type="password" name="new_password" placeholder="รหัสผ่านใหม่" required class="w-full p-3 bg-gray-700 rounded mb-3">
            <input type="password" name="confirm_password" placeholder="ยืนยันรหัสผ่าน" required class="w-full p-3 bg-gray-700 rounded mb-4">
            <div class="flex space-x-2">
                <button type="submit" class="flex-1 bg-red-600 text-white py-3 rounded btn">บันทึก</button>
                <button type="button" onclick="closeChangePasswordModal()" class="flex-1 bg-gray-600 text-white py-3 rounded">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT POST MODAL -->
<div id="editPostModal" class="fixed inset-0 hidden flex items-center justify-center z-50 bg-black bg-opacity-50">
    <div class="bg-gray-800 p-6 rounded-lg max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
        <h3 class="text-2xl font-bold mb-4 text-red-500">แก้ไข Resource Pack</h3>
        <form id="editPostForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_post">
            <input type="hidden" id="editPostId" name="post_id">
            <input type="text" id="editTitle" name="title" required class="w-full p-3 bg-gray-700 rounded mb-3">
            <textarea id="editDescription" name="description" required class="w-full p-3 bg-gray-700 rounded mb-3" rows="3"></textarea>
            <input type="file" name="file" accept=".mcpack,.zip" class="w-full p-3 bg-gray-600 rounded mb-4">
            <div class="flex space-x-2">
                <button type="submit" class="flex-1 bg-red-600 text-white py-3 rounded btn">บันทึก</button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-600 text-white py-3 rounded">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('changePasswordForm').onsubmit = async e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    const res = await fetch('dashboard.php', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData});
    const data = await res.json();
    if(data.success) { showToast('สำเร็จ', data.message); closeChangePasswordModal(); }
    else showToast('Error', data.message, 'error');
};
</script>