<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateEmployeeRequest;
use App\Dto\UpdateEmployeeRequest;
use App\Entity\Employee;
use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\EmailAlreadyUsedException;
use App\Exception\EmployeeNotFoundException;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /** @return list<Employee> */
    public function list(): array
    {
        return $this->employees->findAllOrderedByName();
    }

    public function get(int $id): Employee
    {
        return $this->employees->find($id) ?? throw new EmployeeNotFoundException($id);
    }

    public function create(CreateEmployeeRequest $request): Employee
    {
        if (null !== $this->users->findOneByEmail($request->email)) {
            throw new EmailAlreadyUsedException($request->email);
        }

        $blank = new User($request->email, '', UserRole::Employee);
        $user = new User(
            $request->email,
            $this->hasher->hashPassword($blank, $request->password),
            UserRole::Employee,
        );
        $employee = new Employee(
            $user,
            $request->name,
            $request->position,
            $request->contractType,
            $request->maxWeeklyHours,
        );
        $this->employees->save($employee);

        return $employee;
    }

    public function update(int $id, UpdateEmployeeRequest $request): Employee
    {
        $employee = $this->get($id);
        $employee->update($request->name, $request->position, $request->contractType, $request->maxWeeklyHours);
        $this->employees->save($employee);

        return $employee;
    }

    public function deactivate(int $id): Employee
    {
        $employee = $this->get($id);
        $employee->deactivate();
        $this->employees->save($employee);

        return $employee;
    }
}
