import axios from 'axios';
import { createRouter, createWebHistory } from 'vue-router';
import ApiPropsPage from './support/ApiPropsPage.vue';

import LoginPage from './Pages/Auth/LoginPage.vue';
import DashboardPage from './Pages/Admin/DashboardPage.vue';
import RoomListPage from './Pages/Rooms/RoomListPage.vue';
import RoomDetailPage from './Pages/Rooms/RoomDetailPage.vue';
import RoomManagePage from './Pages/Admin/Rooms/RoomManagePage.vue';
import UserListPage from './Pages/Admin/Users/UserListPage.vue';
import AffiliationManagePage from './Pages/Admin/Affiliations/AffiliationManagePage.vue';
import ApprovalListPage from './Pages/Admin/Approvals/ApprovalListPage.vue';
import PaymentListPage from './Pages/Admin/Payments/PaymentListPage.vue';
import ReservationReportPage from './Pages/Admin/Reports/ReservationReportPage.vue';
import CreateReservationPage from './Pages/Reservations/CreateReservationPage.vue';
import MyReservationsPage from './Pages/Reservations/MyReservationsPage.vue';
import UnauthorizedPage from './Pages/Errors/UnauthorizedPage.vue';
import NotFoundPage from './Pages/Errors/NotFoundPage.vue';

const apiPage = (path, endpoint, component, meta = {}) => ({
    path,
    component: ApiPropsPage,
    meta: { endpoint, component, requiresAuth: true, ...meta },
});

const reserveRoles = ['行政人員', '教授', '學生'];
const staffRoles = ['行政人員'];
const adminRoles = ['管理員'];

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/login' },
        { path: '/login', component: LoginPage },
        { path: '/dashboard', component: DashboardPage, meta: { requiresAuth: true } },
        apiPage('/rooms', '/rooms', RoomListPage),
        apiPage('/rooms/:id', (route) => `/rooms/${route.params.id}`, RoomDetailPage),
        apiPage('/admin/rooms', '/admin/rooms', RoomManagePage, { roles: adminRoles }),
        apiPage('/admin/users', '/admin/users', UserListPage, { roles: adminRoles }),
        apiPage('/admin/affiliations', '/admin/affiliations', AffiliationManagePage, { roles: adminRoles }),
        apiPage('/admin/reports/reservations', '/admin/reports/reservations', ReservationReportPage, { roles: staffRoles }),
        apiPage('/reservations/create', '/reservations/create', CreateReservationPage, { roles: reserveRoles }),
        { path: '/reservations', component: MyReservationsPage, meta: { requiresAuth: true, roles: reserveRoles } },
        { path: '/approvals', component: ApprovalListPage, meta: { requiresAuth: true, roles: staffRoles } },
        { path: '/admin/payments', component: PaymentListPage, meta: { requiresAuth: true, roles: staffRoles } },
        { path: '/unauthorized', component: UnauthorizedPage, meta: { requiresAuth: true } },
        { path: '/not-found', component: NotFoundPage },
        { path: '/:pathMatch(.*)*', redirect: '/not-found' },
    ],
});

router.beforeEach(async (to) => {
    if (!to.meta.requiresAuth) {
        return true;
    }

    try {
        const response = await axios.get('/api/user');
        const userRoles = response.data?.roles?.map((role) => role.role_type) || [];
        const requiredRoles = to.meta.roles || [];

        if (requiredRoles.length > 0 && !requiredRoles.some((role) => userRoles.includes(role))) {
            return '/unauthorized';
        }

        return true;
    } catch {
        return '/login';
    }
});

export default router;
